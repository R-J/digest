<?php
/**
 * Send one mail per day
 * Info:
 * Count of new, unread discussions
 * Count of discussions with unread comments
 * Count of new users
 * 3 most commented on discussions
 */
class DigestPlugin extends Gdn_Plugin {
    public function setup() {
        // TODO create PR for touchConfig in Gdn_Config
        touchConfig('Digest.UsersPerTask', 50000)
        $this->structure();
    }

    public function structure() {
        Gdn::structure()
            ->table('User')
            ->column('Digest', 'tinyint(1)', 1)
            ->set();
    }

    public function settingsController_digest_create() {
        /*
         * On setting page, admin has to
         *     1. init the first digest. "If DateDue is filled"
         *     2. period
         * (each x hours beginning from __.__.____)
         */
        $simpleQueue = new SimpleQueuePlugin();
        $simpleQueue->send(
            'DigestSendToAll',
            [
                [
                    'DateDue' => GDN_Format::toDateTime()
                ]
            ]
        );
    }

    public function simpleQueuePlugin_pluginDigest_handler($sender, $args) {

    }

    public function profileController_afterPreferencesDefined_handler($sender) {
        $sender->Preferences['Notifications']['Email.PluginDigest'] = t('Send me a digest.');
    }

    public function simpleQueuePlugin_beforeDigestSendToAll_handler($sender) {
        $task = $sender->EventArguments['Message'];
        // Number of not deleted or banned users, which are subscribed to the digest.
        $userCount = Gdn::sql()->getCount(
            'User',
            ['Banned' => 0, 'Deleted' => 0, 'Digest' => 1]
        );
        // The number of users that a task will be created for in one task.
        $usersPerTask = Gdn::config()->get('Digest.UsersPerTask', 50000);
        // Number of tasks as a result of the above.
        $taskCount = floor($userCount / $usersPerTask);

        // Date due from the task must be passed to the single tasks.
        $dateDue = $task['Body']['DateDue'] ?? GDN_Format::toDateTime();
        $simpleQueue = new SimpleQueuePlugin();
        for ($k = 0; $k <= $taskCount; $k++) {
            $simpleQueue->send(
                'DigestSendToSome',
                [
                    [
                        'Body' => [
                            'Offset' => $k * $usersPerTask,
                            'Limit' => $usersPerTask
                        ],
                        'DateDue' => $dateDue
                    ]
                ]
            );
        }
        $sender->EventArguments['Acknowledged'] = true;
    }

    public function simpleQueuePlugin_beforeDigestSendToSome_handler($sender) {
        $task = $sender->EventArguments['Message'];
        $users = Gdn::sql()->getWhere(
            'User',
            'UserID',
            'asc',
            $task['Body']['Offset'],
            $task['Body']['Limit'],
            ['Banned' => 0, 'Deleted' => 0, 'Digest' => 1]
        )->resultArray();

        // Date due from the task must be passed to the single tasks.
        $dateDue = $task['Body']['DateDue'] ?? GDN_Format::toDateTime();
        $messages = [];
        foreach ($users as $user) {
            $messages[] = [
                ['Body' => [$user]],
                'DateDue' => $dateDue
            ];
        }

        $simpleQueue = new SimpleQueuePlugin();
        $simpleQueue->send(
            'DigestSendToUser',
            $messages
        );

        $sender->EventArguments['Acknowledged'] = true;
    }

    public function simpleQueuePlugin_beforeDigestSendToOne_handler($sender) {
        $task = $sender->EventArguments['Message'];
        try {
            decho($task);
        } catch (Exception $ex) {
            // If sending fails and it hasn't already been unsuccessful for
            // more than 5 times...
            if ($task['CountFailures'] <= 5) {
                // ... try again in 5 minutes.
                $simpleQueue = new SimpleQueuePlugin();
                $simpleQueue->delay([$task['SimpleQueueID']], 5);
            }
            return false;
        }
        $sender->EventArguments['Acknowledged'] = true;
    }
}

/**
 * When plugin is enabled, nothing will happen.
 * On setting page, admin has to
 *     1. init the first digest. "If DateDue is filled"
 *     2. period
 * (each x hours beginning from __.__.____)
 * That will create a task "Digest.SendToAll"
 *
 * The "Digest.SendToAll" task creates several "Digest.SendToSome" tasks:
 *   Get number of users.
 *   Create one task per 50.000 users
 *   Body: [offset = 0]
 *   Body: [offset = 50.000]
 *   etc.
 *
 * "Digest.SendToSome" creates 50.000 "Digest.SendToUser" tasks
 *   Get 50.000 users from db, offset taken from task body
 *   foreach ($users as $user) {
 *       $messages[] = ['Body' => $user];
 *   }
 *
 * "Digest.SendToUser" gathers information for user and sends mail. Simple as that.
 *
 * UNSUBSCRIBE MUST BE IMPLEMENTED!!!
 */



