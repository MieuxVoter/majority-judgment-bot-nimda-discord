<?php declare(strict_types=1);

namespace Nimda\Core;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Nimda\Configuration\Database as Config;
use Nimda\DB;

class Database
{
    public static $manager;

    public static function boot(): void
    {
        printf("Booting database driver - ");
        self::$manager = new Manager;
        self::$manager->addConnection(Config::$config['connections'][Config::$config['default']]);
        self::$manager->setAsGlobal();
        self::$manager->bootEloquent();

        self::installTables();

        $version = (Config::$config['default'] === 'sqlite') ? "sqlite_version()" : "version()";

        printf("%s version %s booted \n",Config::$config['default'], DB::select("select {$version} as version")[0]->version);
    }

    const POLLS = 'polls';
    const PROPOSALS = 'proposals';
    const CHANNELS = 'channels';

    public static function installTables()
    {
        if ( ! DB::schema()->hasTable(self::POLLS)) {
            DB::schema()->create(self::POLLS, function (Blueprint $table) {
                $table->increments('id');
                $table->string('authorId')->nullable();
                $table->string('channelId');
                $table->string('messageId');
                $table->string('triggerMessageId')->nullable();
                $table->string('subject'); // cache? could be read from message?
                $table->string('amountOfGrades');
                $table->timestamp('createdAt', 0)->nullable();
                $table->timestamp('updatedAt', 0)->nullable(); // not used yet
            });
        }

        if ( ! DB::schema()->hasTable(self::PROPOSALS)) {
            DB::schema()->create(self::PROPOSALS, function (Blueprint $table) {
                $table->increments('id');
                $table->integer('pollId');
//                $table->foreign('pollId', 'poll'); // hmmm… help!
                $table->string('channelId'); // could be read through poll ; cached value
                $table->string('authorId')->nullable();
                $table->string('messageId');
                $table->string('triggerMessageId')->nullable();
                $table->string('name');
                $table->timestamp('createdAt', 0)->nullable();
                $table->timestamp('updatedAt', 0)->nullable();  // not used yet
            });
        }

        // Since we don't have migrations and I'm not sure we easily can,
        // let's work on this a while before enabling it:
//        if ( ! DB::schema()->hasTable(self::CHANNELS)) {
//            DB::schema()->create(self::CHANNELS, function (Blueprint $table) {
//                $table->increments('id');
//                $table->string('discordId');  // guildId or something
//                $table->string('joinerId')->nullable();
//                $table->string('joinerUsername')->nullable();
//                $table->string('pollCreationRoles')->default("");
//                $table->string('proposalCreationRoles')->default("");
//                $table->string('voteViaReactionRoles')->default("");
//                $table->integer('usage')->default(0);  // metric? increments when using the bot?
//
//                $table->timestamp('joinedAt', 0)->nullable();
//            });
//        }
    }
}