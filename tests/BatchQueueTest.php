<?php

use Mockery as m;
use PHPUnit\Framework\TestCase;

class BatchQueueTest extends TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testPushProperlyPushesJobOntoDatabase()
    {
        /** @var \LukeWaite\LaravelQueueAwsBatch\Queues\BatchQueue $queue */
        $queue = $this->getMockBuilder('LukeWaite\LaravelQueueAwsBatch\Queues\BatchQueue')->setMethods(null)->setConstructorArgs([
            $database = m::mock('Illuminate\Database\Connection'),
            'table',
            'default',
            '60',
            'jobdefinition',
            $batch = m::mock('Aws\Batch\BatchClient')
        ])->getMock();

        $database->shouldReceive('table')->with('table')->andReturn($query = m::mock('StdClass'));

        $query->shouldReceive('insertGetId')->once()->andReturnUsing(function ($array) {
            $this->assertEquals('default', $array['queue']);
            $this->assertNotNull($array['payload'], 'Payload is not set');
            $this->assertEquals(['data'], json_decode($array['payload'], 1)['data']);
            $this->assertEquals('foo', json_decode($array['payload'], 1)['job']);
            $this->assertEquals(0, $array['attempts']);
            $this->assertNull($array['reserved_at']);
            $this->assertInternalType('int', $array['available_at']);

            return 100;
        });

        $batch->shouldReceive('submitJob')->once()->andReturnUsing(function ($array) {
            $this->assertEquals('jobdefinition', $array['jobDefinition']);
            $this->assertEquals('foo', $array['jobName']);
            $this->assertEquals('default', $array['jobQueue']);
            $this->assertEquals(['jobId' => 100], $array['parameters']);
        });

        $queue->push('foo', ['data']);
    }

    public function testGetJobById()
    {
        /** @var \LukeWaite\LaravelQueueAwsBatch\Queues\BatchQueue $queue */
        $queue = $this->getMockBuilder('LukeWaite\LaravelQueueAwsBatch\Queues\BatchQueue')->setMethods(null)->setConstructorArgs([
            $database = m::mock('Illuminate\Database\Connection'),
            'table',
            'default',
            '60',
            'jobdefinition',
            $batch = m::mock('Aws\Batch\BatchClient')
        ])->getMock();

        $database->shouldReceive('table')->once()->with('table')->andReturn($query = m::mock('StdClass'));
        $query->shouldReceive('where')->once()->with('id', 1)->andReturn($results = m::mock('StdClass'));
        $results->shouldReceive('first')->once()->andReturn($queryResult = m::mock('StdClass'));
        $queryResult->attempts = 0;

        $queue->setContainer(m::mock('Illuminate\Container\Container'));

        $queue->getJobById(1, 'default');
    }

    public function testRelease()
    {
        /** @var \LukeWaite\LaravelQueueAwsBatch\Queues\BatchQueue $queue */
        $queue = $this->getMockBuilder('LukeWaite\LaravelQueueAwsBatch\Queues\BatchQueue')->setMethods(null)->setConstructorArgs([
            $database = m::mock('Illuminate\Database\Connection'),
            'table',
            'default',
            '60',
            'jobdefinition',
            $batch = m::mock('Aws\Batch\BatchClient')
        ])->getMock();

        $database->shouldReceive('table')->once()->with('table')->andReturn($query = m::mock('StdClass'));
        $query->shouldReceive('update')->once()->with([
            'id'          => 4,
            'attempts'    => 1,
            'reserved'    => 0,
            'reserved_at' => null,
        ]);

        $queue->setContainer(m::mock('Illuminate\Container\Container'));

        $job = new \stdClass();
        $job->payload = '{"job":"foo","data":["data"]}';
        $job->id = 4;
        $job->queue = 'default';
        $job->attempts = 1;

        $queue->release('default', $job, 0);
    }

    /**
     * @expectedException LukeWaite\LaravelQueueAwsBatch\Exceptions\UnsupportedException
     */
    public function testPopThrowsException()
    {
        /** @var \LukeWaite\LaravelQueueAwsBatch\Queues\BatchQueue $queue */
        $queue = $this->getMockBuilder('LukeWaite\LaravelQueueAwsBatch\Queues\BatchQueue')->setMethods(null)->setConstructorArgs([
            $database = m::mock('Illuminate\Database\Connection'),
            'table',
            'default',
            '60',
            'jobdefinition',
            $batch = m::mock('Aws\Batch\BatchClient')
        ])->getMock();

        $queue->pop('default');
    }

    /**
     * @expectedException LukeWaite\LaravelQueueAwsBatch\Exceptions\UnsupportedException
     */
    public function testLaterThrowsException()
    {
        /** @var \LukeWaite\LaravelQueueAwsBatch\Queues\BatchQueue $queue */
        $queue = $this->getMockBuilder('LukeWaite\LaravelQueueAwsBatch\Queues\BatchQueue')->setMethods(null)->setConstructorArgs([
            $database = m::mock('Illuminate\Database\Connection'),
            'table',
            'default',
            '60',
            'jobdefinition',
            $batch = m::mock('Aws\Batch\BatchClient')
        ])->getMock();

        $queue->later(10, 'default');
    }
}