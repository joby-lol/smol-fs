<?php

namespace Joby\Smol\Filesystem;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AggregateFileTest extends TestCase
{

    private AggregateFilesystem $mockRoot;

    protected function setUp(): void
    {
        $this->mockRoot = $this->createMock(AggregateFilesystem::class);
    }

    /**
     * Test standard delegation methods route directly to the main (first) file in $files.
     */
    public function test_delegates_standard_operations_to_first_file(): void
    {
        $mainFile = $this->createMock(FileInterface::class);
        $fallbackFile = $this->createMock(FileInterface::class);

        // Fallback file should not receive calls for standard single-file operations
        $fallbackFile->expects($this->never())->method($this->anything());

        $mainFile->expects($this->once())->method('read')->willReturn('hello');
        $mainFile->expects($this->once())->method('write')->with('data')->willReturnSelf();
        $mainFile->expects($this->once())->method('append')->with('more')->willReturnSelf();
        $mainFile->expects($this->once())->method('appendLine')->with('line')->willReturnSelf();
        $mainFile->expects($this->once())->method('copyFrom')->with('/tmp/src')->willReturnSelf();
        $mainFile->expects($this->once())->method('exists')->willReturn(true);
        $mainFile->expects($this->once())->method('extension')->willReturn('txt');
        $mainFile->expects($this->once())->method('filename')->willReturn('test.txt');
        $mainFile->expects($this->once())->method('relativePath')->willReturn('dir/test.txt');
        $mainFile->expects($this->once())->method('size')->willReturn(100);
        $mainFile->expects($this->once())->method('__toString')->willReturn('/root/dir/test.txt');

        $now = new DateTimeImmutable();
        $mainFile->expects($this->once())->method('modified')->willReturn($now);

        $aggregate = new AggregateFile($this->mockRoot, $mainFile, $fallbackFile);

        $this->assertEquals('hello', $aggregate->read());
        $this->assertSame($aggregate, $aggregate->write('data'));
        $this->assertSame($aggregate, $aggregate->append('more'));
        $this->assertSame($aggregate, $aggregate->appendLine('line'));
        $this->assertSame($aggregate, $aggregate->copyFrom('/tmp/src'));
        $this->assertTrue($aggregate->exists());
        $this->assertEquals('txt', $aggregate->extension());
        $this->assertEquals('test.txt', $aggregate->filename());
        $this->assertEquals('dir/test.txt', $aggregate->relativePath());
        $this->assertEquals(100, $aggregate->size());
        $this->assertEquals('/root/dir/test.txt', (string) $aggregate);
        $this->assertSame($now, $aggregate->modified());
    }

    /**
     * Test create: false scenario where factory passes only existing files.
     */
    public function test_delegates_to_first_existing_file_when_created_without_placeholders(): void
    {
        $existingPrimary = $this->createMock(FileInterface::class);
        $existingFallback = $this->createMock(FileInterface::class);

        $existingPrimary->expects($this->once())->method('read')->willReturn('primary content');
        $existingPrimary->expects($this->once())->method('exists')->willReturn(true);

        // Fallback file shouldn't be touched for single-file reads
        $existingFallback->expects($this->never())->method('read');

        $aggregate = new AggregateFile($this->mockRoot, $existingPrimary, $existingFallback);

        $this->assertTrue($aggregate->exists());
        $this->assertEquals('primary content', $aggregate->read());
    }

    /**
     * Test create: true scenario where factory passes nonexistent placeholder files.
     * Writes target $files[0] to create the file in the primary root.
     */
    public function test_writes_to_primary_placeholder_when_created_for_creation(): void
    {
        $nonexistentPrimaryPlaceholder = $this->createMock(FileInterface::class);
        $nonexistentFallbackPlaceholder = $this->createMock(FileInterface::class);

        $nonexistentPrimaryPlaceholder->expects($this->once())
            ->method('write')
            ->with('new content')
            ->willReturnSelf();

        // Secondary placeholders should not be written to
        $nonexistentFallbackPlaceholder->expects($this->never())->method('write');

        $aggregate = new AggregateFile(
            $this->mockRoot,
            $nonexistentPrimaryPlaceholder,
            $nonexistentFallbackPlaceholder,
        );

        $this->assertSame($aggregate, $aggregate->write('new content'));
    }

    /**
     * Test that delete() deletes ALL files in the aggregate stack.
     */
    public function test_delete_deletes_all_underlying_files(): void
    {
        $file1 = $this->createMock(FileInterface::class);
        $file2 = $this->createMock(FileInterface::class);
        $file3 = $this->createMock(FileInterface::class);

        $file1->expects($this->once())->method('delete')->willReturnSelf();
        $file2->expects($this->once())->method('delete')->willReturnSelf();
        $file3->expects($this->once())->method('delete')->willReturnSelf();

        $aggregate = new AggregateFile($this->mockRoot, $file1, $file2, $file3);

        $this->assertSame($aggregate, $aggregate->delete());
    }

    /**
     * Test rawExistingFiles() yields only files that return exists() === true.
     */
    public function test_raw_existing_files_filters_by_existence(): void
    {
        $file1 = $this->createMock(FileInterface::class);
        $file2 = $this->createMock(FileInterface::class);
        $file3 = $this->createMock(FileInterface::class);

        $file1->method('exists')->willReturn(true);
        $file2->method('exists')->willReturn(false);
        $file3->method('exists')->willReturn(true);

        $aggregate = new AggregateFile($this->mockRoot, $file1, $file2, $file3);

        $existing = iterator_to_array($aggregate->rawExistingFiles());

        $this->assertCount(2, $existing);
        $this->assertSame($file1, $existing[0]);
        $this->assertSame($file3, $existing[1]);
    }

    /**
     * Test readConcatenated() merges existing file contents with custom separators.
     */
    public function test_read_concatenated_merges_existing_files(): void
    {
        $file1 = $this->createMock(FileInterface::class);
        $file2 = $this->createMock(FileInterface::class);
        $file3 = $this->createMock(FileInterface::class);

        $file1->method('exists')->willReturn(true);
        $file1->method('read')->willReturn('/* Core */');

        $file2->method('exists')->willReturn(false); // Non-existent placeholder skipped

        $file3->method('exists')->willReturn(true);
        $file3->method('read')->willReturn('/* Override */');

        $aggregate = new AggregateFile($this->mockRoot, $file1, $file2, $file3);

        $result = $aggregate->readConcatenated(separator: "\n---\n");

        $this->assertEquals("/* Core */\n---\n/* Override */", $result);
    }

    /**
     * Test readConcatenated() returns false if no files in the aggregate exist on disk.
     */
    public function test_read_concatenated_returns_false_when_no_files_exist(): void
    {
        $file1 = $this->createMock(FileInterface::class);
        $file2 = $this->createMock(FileInterface::class);

        $file1->method('exists')->willReturn(false);
        $file2->method('exists')->willReturn(false);

        $aggregate = new AggregateFile($this->mockRoot, $file1, $file2);

        $this->assertFalse($aggregate->readConcatenated());
    }

    /**
     * Test rawFiles() flattens nested AggregateFile instances recursively.
     */
    public function test_raw_files_flattens_nested_aggregate_files_recursively(): void
    {
        $leaf1 = $this->createMock(FileInterface::class);
        $leaf2 = $this->createMock(FileInterface::class);
        $leaf3 = $this->createMock(FileInterface::class);

        // Nested aggregate containing leaf1 & leaf2
        $nestedAggregate = new AggregateFile($this->mockRoot, $leaf1, $leaf2);

        // Outer aggregate containing $nestedAggregate and $leaf3
        $outerAggregate = new AggregateFile($this->mockRoot, $nestedAggregate, $leaf3);

        $allRawFiles = iterator_to_array($outerAggregate->rawFiles());

        $this->assertCount(3, $allRawFiles);
        $this->assertSame($leaf1, $allRawFiles[0]);
        $this->assertSame($leaf2, $allRawFiles[1]);
        $this->assertSame($leaf3, $allRawFiles[2]);
    }

}
