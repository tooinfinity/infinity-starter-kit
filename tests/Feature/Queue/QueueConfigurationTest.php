<?php

declare(strict_types=1);

it('does not force after_commit on queue connections by default', function (): void {
    foreach (['database', 'redis', 'sqs', 'beanstalkd'] as $connection) {
        expect(config("queue.connections.{$connection}.after_commit"))->toBeFalse();
    }
});
