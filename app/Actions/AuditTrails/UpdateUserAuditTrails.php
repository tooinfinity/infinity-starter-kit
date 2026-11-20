<?php

declare(strict_types=1);

namespace App\Actions\AuditTrails;

use App\Models\User;
use Illuminate\Database\Query\Builder;
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

        $records = DB::table('activity_log')
            ->where(function (Builder $query) use ($userId, $userMorphs): void {
                $query->where(function (Builder $actorQuery) use ($userId, $userMorphs): void {
                    $actorQuery->whereIn('causer_type', $userMorphs)
                        ->where('causer_id', $userId);
                })
                    ->orWhere(function (Builder $subjectQuery) use ($userId, $userMorphs): void {
                        $subjectQuery->whereIn('subject_type', $userMorphs)
                            ->where('subject_id', $userId);
                    });
            })
            ->lockForUpdate()
            ->get();

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
                : (is_array($record->properties) ? $record->properties : []);
            /** @var array<string, mixed> $properties */
            $properties = is_array($rawProperties) ? $rawProperties : [];

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

            if ($isSubject || $isActor) {
                /** @var array<string, mixed>|null $oldValues */
                $oldValues = isset($properties['old']) && is_array($properties['old'])
                    ? $properties['old']
                    : null;

                /** @var array<string, mixed>|null $newValues */
                $newValues = isset($properties['attributes']) && is_array($properties['attributes'])
                    ? $properties['attributes']
                    : (isset($properties['new']) && is_array($properties['new']) ? $properties['new'] : null);

                $properties['old'] = $this->scrubValues($oldValues);
                $properties['attributes'] = $this->scrubValues($newValues);
                if (isset($properties['new'])) {
                    unset($properties['new']);
                }
            }

            $rawTags = $properties['tags'] ?? null;
            /** @var list<string> $tags */
            $tags = is_array($rawTags) ? array_values(array_filter($rawTags, is_string(...))) : [];
            if (! in_array('anonymized', $tags, true)) {
                $tags[] = 'anonymized';
            }

            $properties['tags'] = $tags;

            DB::table('activity_log')->where('id', $record->id)->update([
                'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function scrubValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $result = [];

        foreach ($values as $key => $value) {
            $normalizedKey = mb_strtolower((string) $key);

            if (in_array($normalizedKey, ['name', 'email', 'actor_name', 'actor_email'], true)) {
                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $result[$key] = $this->scrubValues($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
