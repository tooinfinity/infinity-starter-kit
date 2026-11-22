<?php

declare(strict_types=1);

namespace App\Actions\AuditTrails;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class UpdateUserAuditTrails
{
    public function handle(User $user, ?bool $anonymize = null): void
    {
        $shouldAnonymize = $anonymize ?? config()->boolean('audit.anonymize_on_delete', true);

        if (! $shouldAnonymize) {
            return;
        }

        $userId = (string) $user->id;
        $userMorphs = array_values(array_unique([
            $user->getMorphClass(),
            User::class,
            'user',
            'users',
        ]));

        $query = DB::table('activity_log')
            ->select(['id', 'causer_type', 'causer_id', 'subject_type', 'subject_id', 'properties', 'attribute_changes'])
            ->where(function (Builder $q) use ($userId, $userMorphs): void {
                $q->where(function (Builder $actorQuery) use ($userId, $userMorphs): void {
                    $actorQuery->whereIn('causer_type', $userMorphs)
                        ->where('causer_id', $userId);
                })
                    ->orWhere(function (Builder $subjectQuery) use ($userId, $userMorphs): void {
                        $subjectQuery->whereIn('subject_type', $userMorphs)
                            ->where('subject_id', $userId);
                    });
            });

        /** @param Collection<int, object> $records */
        DB::transaction(fn (): bool => $query->lockForUpdate()->orderBy('id')->chunkById(200, function (Collection $records) use ($userId, $userMorphs): void {
            foreach ($records as $record) {
                $isActor = is_string($record->causer_type)
                    && in_array($record->causer_type, $userMorphs, true)
                    && (is_string($record->causer_id) || is_int($record->causer_id))
                    && (string) $record->causer_id === $userId;

                $isSubject = is_string($record->subject_type)
                    && in_array($record->subject_type, $userMorphs, true)
                    && (is_string($record->subject_id) || is_int($record->subject_id))
                    && (string) $record->subject_id === $userId;

                $rawProperties = is_string($record->properties)
                    ? json_decode($record->properties, true)
                    : [];
                /** @var array<string, mixed> $properties */
                $properties = is_array($rawProperties) ? $rawProperties : [];

                $rawChanges = is_string($record->attribute_changes)
                    ? json_decode($record->attribute_changes, true)
                    : null;
                /** @var array<string, mixed>|null $attributeChanges */
                $attributeChanges = is_array($rawChanges) ? $rawChanges : null;

                if ($isActor) {
                    if (isset($properties['actor']) && is_array($properties['actor'])) {
                        $properties['actor']['name'] = null;
                        $properties['actor']['email'] = null;
                    }

                    if (isset($properties['request']) && is_array($properties['request'])) {
                        $properties['request']['ip_address'] = null;
                        $properties['request']['user_agent'] = null;
                    }
                }

                if (($isSubject || $isActor) && $attributeChanges !== null) {
                    /** @var array<string, mixed>|null $oldChanges */
                    $oldChanges = isset($attributeChanges['old']) && is_array($attributeChanges['old'])
                        ? $attributeChanges['old']
                        : null;

                    /** @var array<string, mixed>|null $newChanges */
                    $newChanges = isset($attributeChanges['attributes']) && is_array($attributeChanges['attributes'])
                        ? $attributeChanges['attributes']
                        : null;

                    $attributeChanges['old'] = $this->scrubValues($oldChanges);
                    $attributeChanges['attributes'] = $this->scrubValues($newChanges);
                }

                $rawTags = $properties['tags'] ?? null;
                /** @var list<string> $tags */
                $tags = is_array($rawTags) ? array_values(array_filter($rawTags, is_string(...))) : [];
                if (! in_array('anonymized', $tags, true)) {
                    $tags[] = 'anonymized';
                }

                $properties['tags'] = $tags;

                DB::table('activity_log')->where('id', $record->id)->update([
                    ...($isActor ? ['ip_address' => null, 'user_agent' => null] : []),
                    'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
                    'attribute_changes' => $attributeChanges !== null
                        ? json_encode($attributeChanges, JSON_THROW_ON_ERROR)
                        : null,
                ]);
            }
        }));
    }

    /**
     * @param  array<array-key, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function scrubValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $result = [];

        foreach ($values as $key => $value) {
            $stringKey = (string) $key;
            $normalizedKey = mb_strtolower($stringKey);

            if (in_array($normalizedKey, ['name', 'email', 'actor_name', 'actor_email'], true)) {
                continue;
            }

            if (is_array($value)) {
                $result[$stringKey] = $this->scrubValues($value);
            } else {
                $result[$stringKey] = $value;
            }
        }

        return $result;
    }
}
