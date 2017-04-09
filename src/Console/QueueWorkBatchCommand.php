<?php
/**
 * Laravel Queue for AWS Batch.
 *
 * @author    Luke Waite <lwaite@gmail.com>
 * @copyright 2017 Luke Waite
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 *
 * @link      https://github.com/lukewaite/laravel-queue-aws-batch
 */

namespace LukeWaite\LaravelQueueAwsBatch\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker;
use LukeWaite\LaravelQueueAwsBatch\Exceptions\JobNotFoundException;
use LukeWaite\LaravelQueueAwsBatch\Exceptions\UnsupportedException;
use LukeWaite\LaravelQueueAwsBatch\Queues\BatchQueue;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class QueueWorkBatchCommand extends WorkCommand
{
    protected $name = 'queue:work-batch';

    protected $description = 'Run a Job for the AWS Batch queue';

    protected $signature = 'queue:work-batch {connection} {job_id} {--tries=}';

    protected $manager;
    protected $exceptions;

    public function __construct(QueueManager $manager, Worker $worker, Handler $exceptions)
    {
        parent::__construct($worker);
        $this->manager = $manager;
        $this->exceptions = $exceptions;
    }

    public function fire()
    {
        try {
            $this->runJob();
        } catch (\Exception $e) {
            $this->exceptions->report($e);
            throw $e;
        } catch (\Throwable $e) {
            $this->exceptions->report(new FatalThrowableError($e));
            throw $e;
        }
    }

    // TOOD: Refactor out the logic here into an extension of the Worker class
    protected function runJob()
    {
        $connectionName = $this->argument('connection');
        $jobId = $this->argument('job_id');

        /** @var BatchQueue $connection */
        $connection = $this->manager->connection($connectionName);

        if (!$connection instanceof BatchQueue) {
            throw new UnsupportedException('queue:work-batch can only be run on batch queues');
        }

        $job = $connection->getJobById($jobId, $connectionName);

        // If we're able to pull a job off of the stack, we will process it and
        // then immediately return back out.
        if (!is_null($job)) {
            $this->worker->process(
                $this->manager->getName($connectionName),
                $job,
                $this->gatherWorkerOptions()
            );
        }

        // If we hit this point, we haven't processed our job
        throw new JobNotFoundException('No job was returned');
    }
}
