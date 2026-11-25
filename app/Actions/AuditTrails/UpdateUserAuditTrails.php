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

        DB::transaction(function () use ($userId, $userMorphs): void {
            $activityRecords = DB::table('activity_log')
                ->where(function (Builder $query) use ($userId, $userMorphs): void {
                    $query->where(function (Builder $causerQuery) use ($userId, $userMorphs): void {
                        $causerQuery->where('causer_id', $userId)
                            ->whereIn('causer_type', $userMorphs);
                    })->orWhere(function (Builder $subjectQuery) use ($userId, $userMorphs): void {
                        $subjectQuery->where('subject_id', $userId)
                            ->whereIn('subject_type', $userMorphs);
                    });
                })
                ->get();

            foreach ($activityRecords as $activityRecord) {
                $isCauser = ($activityRecord->causer_id === $userId && in_array($activityRecord->causer_type, $userMorphs, true));

                $activityUpdates = [];

                if ($isCauser) {
                    $activityUpdates['causer_id'] = null;
                    $activityUpdates['causer_type'] = null;
                    $activityUpdates['ip_address'] = null;
                    $activityUpdates['user_agent'] = null;
                }

                /** @var array<string, mixed>|null $rawProperties */
                $rawProperties = is_string($activityRecord->properties)
                    ? json_decode($activityRecord->properties, true)
                    : null;

                $properties = is_array($rawProperties) ? $rawProperties : [];

                if ($isCauser && isset($properties['actor']) && is_array($properties['actor'])) {
                    $properties['actor']['name'] = null;
                    $properties['actor']['email'] = null;
                }

                if (isset($properties['old']) && is_array($properties['old'])) {
                    $properties['old'] = $this->scrubValues($properties['old']);
                }

                if (isset($properties['attributes']) && is_array($properties['attributes'])) {
                    $properties['attributes'] = $this->scrubValues($properties['attributes']);
                }

                $rawActivityTags = isset($properties['tags']) && is_array($properties['tags'])
                    ? $properties['tags']
                    : [];
                /** @var list<string> $activityTags */
                $activityTags = array_values(array_filter($rawActivityTags, is_string(...)));

                if (! in_array('anonymized', $activityTags, true)) {
                    $activityTags[] = 'anonymized';
                }

                $properties['tags'] = $activityTags;

                $activityUpdates['properties'] = json_encode($properties, JSON_THROW_ON_ERROR);

                /** @var array<string, mixed>|null $rawChanges */
                $rawChanges = is_string($activityRecord->attribute_changes)
                    ? json_decode($activityRecord->attribute_changes, true)
                    : null;

                if (is_array($rawChanges)) {
                    if (isset($rawChanges['old']) && is_array($rawChanges['old'])) {
                        $rawChanges['old'] = $this->scrubValues($rawChanges['old']);
                    }

                    if (isset($rawChanges['attributes']) && is_array($rawChanges['attributes'])) {
                        $rawChanges['attributes'] = $this->scrubValues($rawChanges['attributes']);
                    }

                    $activityUpdates['attribute_changes'] = json_encode($rawChanges, JSON_THROW_ON_ERROR);
                }

                DB::table('activity_log')->where('id', $activityRecord->id)->update($activityUpdates);
            }
        });
    }

    /**
     * @param  array<mixed, mixed>|null  $values
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
                $result[(string) $key] = $this->scrubValues($value);
            } else {
                $result[(string) $key] = $value;
            }
        }

        return $result;
    }
}
