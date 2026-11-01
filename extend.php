<?php

use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Hooey\PollGuard\Access\DiscussionPollPolicy;
use Hooey\PollGuard\Api\Controllers\PollEligibilityController;

return [
    (new Extend\Policy())
        ->modelPolicy(Discussion::class, DiscussionPollPolicy::class),

    (new Extend\Routes('api'))
        ->get('/hooey/admin/poll-eligibility', 'hooey.admin.poll-eligibility', PollEligibilityController::class),
];
