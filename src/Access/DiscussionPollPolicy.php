<?php

namespace Hooey\PollGuard\Access;

use Flarum\Discussion\Discussion;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

/**
 * Voting eligibility for committee polls.
 *
 * FoF Polls delegates the vote check to `$actor->can('polls.vote',
 * $discussion)` (see PollPolicy::vote). Dotted abilities have no method of
 * their own on a policy, so they arrive here through can(). The Gate gives
 * DENY priority over ALLOW, so a DENY here always wins over any ALLOW
 * from other policies (e.g. a global polls permission).
 *
 * Scope is deliberately narrow: only discussions carrying the committee
 * tag (id 7) are affected. Every other discussion returns null, leaving
 * existing behaviour (including public-board polls) untouched.
 */
class DiscussionPollPolicy extends AbstractPolicy
{
    private const COMMITTEE_TAG_ID = 7;

    // Groups barred from voting in committee polls.
    private const DENIED_GROUP_IDS = [11, 12];

    // Groups explicitly allowed to vote in committee polls.
    // Committee votes belong to certified residents only (group 6):
    // committee members (group 7) vote through their resident group,
    // not through group 7, and officials (group 13) have no vote here.
    // (Former owner group 5 and preparation group 9 were merged away in
    // the 2026-10-26 simplification; groups 8/10/14/15 were deleted in
    // the 2026-10-26 group cleanup.)
    private const ALLOWED_GROUP_IDS = [6];

    /** Exposed for the admin vote-eligibility page (single source of truth). */
    public static function deniedGroupIds(): array
    {
        return self::DENIED_GROUP_IDS;
    }

    public static function allowedGroupIds(): array
    {
        return self::ALLOWED_GROUP_IDS;
    }

    public function can(User $actor, string $ability, Discussion $discussion): ?string
    {
        if ($ability !== 'polls.vote') {
            return null;
        }

        // Guests cannot vote anyway (the votes endpoint requires auth);
        // admins fall through to the standard admin bypass.
        if ($actor->isGuest() || $actor->isAdmin()) {
            return null;
        }

        $tagIds = $discussion->tags->pluck('id')->all();

        if (! in_array(self::COMMITTEE_TAG_ID, $tagIds, true)) {
            return null;
        }

        $groupIds = $actor->groups->pluck('id')->all();

        // Deny wins over allow, including for users in several groups.
        if (! empty(array_intersect($groupIds, self::DENIED_GROUP_IDS))) {
            return $this->deny();
        }

        if (! empty(array_intersect($groupIds, self::ALLOWED_GROUP_IDS))) {
            return $this->allow();
        }

        return null;
    }
}
