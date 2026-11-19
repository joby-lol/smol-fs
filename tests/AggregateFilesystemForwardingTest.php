<?php

namespace Joby\Smol\Filesystem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AggregateFilesystemForwardingTest extends TestCase
{

    private function mockFile(string $path): FileInterface
    {
        $file = $this->createMock(FileInterface::class);
        $file->method('__toString')->willReturn($path);
        return $file;
    }

    private function mockContainingFilesystem(): FilesystemInterface
    {
        $fs = $this->createMock(FilesystemInterface::class);
        $fs->method('contains')->willReturn(true);
        return $fs;
    }

    public static function moveInFlags(): array
    {
        return [
            'uploads allowed'        => [true, true],
            'uploads denied'         => [false, false],
            'overwrite only'         => [true, false],
            'upload permission only' => [false, true],
        ];
    }

    #[DataProvider('moveInFlags')]
    public function test_move_in_forwards_all_arguments(
        bool $allowOverwrite,
        bool $allowUploadedFiles,
    ): void
    {
        $fs = $this->mockContainingFilesystem();
        $destination = $this->mockFile('/root/imported.txt');

        $fs->expects($this->once())
            ->method('moveIn')
            ->with(
                '/external/source.txt',
                $this->identicalTo($destination),
                $allowOverwrite,
                $allowUploadedFiles,
            );

        $aggregate = new AggregateFilesystem($fs);

        $aggregate->moveIn(
            '/external/source.txt',
            $destination,
            $allowOverwrite,
            $allowUploadedFiles,
        );
    }

    public function test_copy_in_forwards_all_arguments(): void
    {
        $fs = $this->mockContainingFilesystem();
        $destination = $this->mockFile('/root/copied.txt');

        $fs->expects($this->once())
            ->method('copyIn')
            ->with(
                '/external/source.txt',
                $this->identicalTo($destination),
                false,
            );

        $aggregate = new AggregateFilesystem($fs);
        $aggregate->copyIn(
            '/external/source.txt',
            $destination,
            false,
        );
    }

    public function test_copy_delegates_to_copy_in(): void
    {
        $fs = $this->mockContainingFilesystem();
        $source = $this->mockFile('/root/source.txt');
        $destination = $this->mockFile('/root/copied.txt');

        $fs->expects($this->once())
            ->method('copyIn')
            ->with(
                '/root/source.txt',
                $this->identicalTo($destination),
                true,
            );

        $aggregate = new AggregateFilesystem($fs);
        $aggregate->copy($source, $destination, true);
    }

    public function test_move_delegates_to_move_in(): void
    {
        $fs = $this->mockContainingFilesystem();
        $source = $this->mockFile('/root/source.txt');
        $destination = $this->mockFile('/root/moved.txt');

        $fs->expects($this->once())
            ->method('moveIn')
            ->with(
                '/root/source.txt',
                $this->identicalTo($destination),
                false,
            );

        $aggregate = new AggregateFilesystem($fs);
        $aggregate->move($source, $destination, false);
    }

    public function test_copy_out_forwards_all_arguments(): void
    {
        $fs = $this->mockContainingFilesystem();
        $source = $this->mockFile('/root/source.txt');

        $fs->expects($this->once())
            ->method('copyOut')
            ->with(
                $this->identicalTo($source),
                '/external/copied.txt',
                false,
            );

        $aggregate = new AggregateFilesystem($fs);
        $aggregate->copyOut($source, '/external/copied.txt', false);
    }

    public function test_move_out_forwards_all_arguments(): void
    {
        $fs = $this->mockContainingFilesystem();
        $source = $this->mockFile('/root/source.txt');

        $fs->expects($this->once())
            ->method('moveOut')
            ->with(
                $this->identicalTo($source),
                '/external/moved.txt',
                true,
            );

        $aggregate = new AggregateFilesystem($fs);
        $aggregate->moveOut($source, '/external/moved.txt', true);
    }

    public function test_copy_in_routes_to_matching_filesystem(): void
    {
        $first = $this->createMock(FilesystemInterface::class);
        $second = $this->createMock(FilesystemInterface::class);

        $destination = $this->mockFile('/second/copied.txt');

        $first->method('contains')->willReturn(false);
        $second->method('contains')->willReturn(true);

        $first->expects($this->never())->method('copyIn');

        $second->expects($this->once())
            ->method('copyIn')
            ->with(
                '/external/source.txt',
                $this->identicalTo($destination),
                true,
            );

        $aggregate = new AggregateFilesystem($first, $second);

        $aggregate->copyIn(
            '/external/source.txt',
            $destination,
            true,
        );
    }

}
