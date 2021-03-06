<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\{Profile, User};
use DB;

class FixUsernames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:usernames';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix invalid usernames';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Collecting data ...');

        $affected = collect([]);

        $users = User::chunk(100, function($users) use($affected) {
            foreach($users as $user) {
                $val = str_replace(['-', '_'], '', $user->username);
                if(!ctype_alnum($val)) {
                    $this->info('Found invalid username: ' . $user->username);
                    $affected->push($user);
                }
            }
        });
        if($affected->count() > 0) {
            $this->info('Found: ' . $affected->count() . ' affected usernames');

            $opts = [
                'Random replace (assigns random username)',
                'Best try replace (assigns alpha numeric username)',
                'Manual replace (manually set username)'
            ];

            foreach($affected as $u) {
                $old = $u->username;
                $opt = $this->choice('Select fix method:', $opts, 0);

                switch ($opt) {
                    case $opts[0]:
                        $new = "user_" . str_random(6);
                        $this->info('New username: ' . $new);
                        break;

                    case $opts[1]:
                        $new = filter_var($old, FILTER_SANITIZE_STRING|FILTER_FLAG_STRIP_LOW);
                        if(strlen($new) < 6) {
                            $new = $new . '_' . str_random(4);
                        }
                        $this->info('New username: ' . $new);
                        break;

                    case $opts[2]:
                        $new = $this->ask('Enter new username:');
                        $this->info('New username: ' . $new);
                        break;
                    
                    default:
                        $new = "user_" . str_random(6);
                        break;
                }

                DB::transaction(function() use($u, $new) {
                    $profile = $u->profile;
                    $profile->username = $new;
                    $u->username = $new;
                    $u->save();
                    $profile->save();
                });
                $this->info('Selected: ' . $opt);
            }

            $this->info('Fixed ' . $affected->count() . ' usernames!');
        }
    }
}
