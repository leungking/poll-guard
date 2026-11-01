<?php

namespace Hooey\PollGuard\Api\Controllers;

use Flarum\Group\Group;
use Flarum\Group\Permission;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Hooey\PollGuard\Access\DiscussionPollPolicy;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Admin vote-eligibility overview, computed live (nothing hardcoded in
 * the frontend): the tag7 discussion.polls.vote grants come from
 * group_permission, the deny/allow sets come from the policy itself.
 * Effective result per role group: policy-deny wins, then grant or
 * policy-allow, otherwise denied for lack of any grant.
 */
class PollEligibilityController implements RequestHandlerInterface
{
    private const COMMITTEE_TAG_ID = 7;

    /** Role groups shown in the matrix (Admin bypasses, Guest cannot vote). */
    private const MATRIX_GROUP_IDS = [6, 7, 11, 12, 13];

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        if ($actor->isGuest() || ! $actor->isAdmin()) {
            throw new PermissionDeniedException();
        }

        $granted = [];

        foreach (Permission::where('permission', 'tag'.self::COMMITTEE_TAG_ID.'.discussion.polls.vote')->get() as $row) {
            $granted[] = (int) $row->group_id;
        }

        $denied = DiscussionPollPolicy::deniedGroupIds();
        $allowed = DiscussionPollPolicy::allowedGroupIds();

        $names = Group::whereIn('id', array_unique(array_merge(
            self::MATRIX_GROUP_IDS, $granted, $denied, $allowed
        )))->get()->keyBy('id');

        $nameOf = function (int $id) use ($names): ?string {
            return isset($names[$id]) ? $names[$id]->name_singular : null;
        };

        $listOf = function (array $ids) use ($nameOf): array {
            $out = [];

            foreach ($ids as $id) {
                $out[] = ['id' => (int) $id, 'name' => $nameOf((int) $id)];
            }

            return $out;
        };

        $matrix = [];

        foreach (self::MATRIX_GROUP_IDS as $id) {
            if (in_array($id, $denied, true)) {
                $result = 'denied';
                $reason = 'policy-deny';
            } elseif (in_array($id, $granted, true)) {
                $result = 'allowed';
                $reason = 'grant';
            } elseif (in_array($id, $allowed, true)) {
                $result = 'allowed';
                $reason = 'policy-allow';
            } else {
                $result = 'denied';
                $reason = 'no-grant';
            }

            $matrix[] = ['id' => $id, 'name' => $nameOf($id), 'result' => $result, 'reason' => $reason];
        }

        return new JsonResponse(['data' => [
            'committee_tag_id' => self::COMMITTEE_TAG_ID,
            'vote_grant_groups' => $listOf($granted),
            'denied_groups' => $listOf($denied),
            'allowed_by_policy_groups' => $listOf($allowed),
            'matrix' => $matrix,
        ]]);
    }
}
