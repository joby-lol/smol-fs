<?php

namespace Joby\Smol\Filesystem;

use PHPUnit\Framework\TestCase;

class AggregateFilesystemTest extends TestCase
{

    private string $tempPath1;

    private string $tempPath2;

    private Filesystem $fs1;

    private Filesystem $fs2;

    protected function setUp(): void
    {
        $this->tempPath1 = sys_get_temp_dir() . '/smol_fs_agg_test_1_' . uniqid();
        $this->tempPath2 = sys_get_temp_dir() . '/smol_fs_agg_test_2_' . uniqid();

        mkdir($this->tempPath1, 0777, true);
        mkdir($this->tempPath2, 0777, true);

        $this->fs1 = new Filesystem($this->tempPath1);
        $this->fs2 = new Filesystem($this->tempPath2);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempPath1);
        $this->removeDirectory($this->tempPath2);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Test addFilesystem() priority ordering within the default (medium) priority tier.
     */
    public function test_add_filesystem_manages_priority_order(): void
    {
        $this->fs1->file('config.json', create: true)->write('Primary Content');
        $this->fs2->file('config.json', create: true)->write('Secondary Content');

        // Test default priority order (fs1 is primary)
        $aggregate = new AggregateFilesystem($this->fs1);
        $aggregate->addFilesystem($this->fs2); // Appends to end of medium tier

        $this->assertEquals('Primary Content', $aggregate->file('config.json')->read());

        // Test adding to front of medium tier ($add_to_front = true)
        $fs3Path = sys_get_temp_dir() . '/smol_fs_agg_test_3_' . uniqid();
        mkdir($fs3Path, 0777, true);
        $fs3 = new Filesystem($fs3Path);
        $fs3->file('config.json', create: true)->write('Override Content');

        $aggregate->addFilesystem($fs3, add_to_front: true);

        $this->assertEquals('Override Content', $aggregate->file('config.json')->read());

        $this->removeDirectory($fs3Path);
    }

    /**
     * Test that high priority filesystems override medium priority filesystems, 
     * which in turn override low priority filesystems regardless of registration order.
     */
    public function test_priority_tier_precedence_ordering(): void
    {
        $fs3Path = sys_get_temp_dir() . '/smol_fs_agg_test_3_' . uniqid();
        mkdir($fs3Path, 0777, true);
        $fs3 = new Filesystem($fs3Path);

        $this->fs1->file('config.json', create: true)->write('Medium Content');
        $this->fs2->file('config.json', create: true)->write('Low Content');
        $fs3->file('config.json', create: true)->write('High Content');

        // Initialize with fs1 in constructor (Medium Priority)
        $aggregate = new AggregateFilesystem($this->fs1);

        // Add Low priority first, then High priority
        $aggregate->addLowPriorityFilesystem($this->fs2);
        $aggregate->addHighPriorityFilesystem($fs3);

        // High priority (fs3) must take precedence
        $this->assertEquals('High Content', $aggregate->file('config.json')->read());

        // Delete high-priority file to verify fallback to Medium (fs1)
        $fs3->file('config.json')->delete();
        $this->assertEquals('Medium Content', $aggregate->file('config.json')->read());

        // Delete medium-priority file to verify fallback to Low (fs2)
        $this->fs1->file('config.json')->delete();
        $this->assertEquals('Low Content', $aggregate->file('config.json')->read());

        $this->removeDirectory($fs3Path);
    }

    /**
     * Test that add_to_front within the same priority tier prepends to that specific tier.
     */
    public function test_add_to_front_within_same_priority_tier(): void
    {
        $fs3Path = sys_get_temp_dir() . '/smol_fs_agg_test_3_' . uniqid();
        mkdir($fs3Path, 0777, true);
        $fs3 = new Filesystem($fs3Path);

        $this->fs1->file('app.txt', create: true)->write('High Base');
        $this->fs2->file('app.txt', create: true)->write('High Appended');
        $fs3->file('app.txt', create: true)->write('High Prepended');

        $aggregate = new AggregateFilesystem();

        // Add High Base
        $aggregate->addHighPriorityFilesystem($this->fs1);

        // Add High Appended (default add_to_front = false, goes after High Base)
        $aggregate->addHighPriorityFilesystem($this->fs2, add_to_front: false);
        $this->assertEquals('High Base', $aggregate->file('app.txt')->read());

        // Add High Prepended (add_to_front = true, goes before High Base)
        $aggregate->addHighPriorityFilesystem($fs3, add_to_front: true);
        $this->assertEquals('High Prepended', $aggregate->file('app.txt')->read());

        $this->removeDirectory($fs3Path);
    }

    /**
     * Test adding to low priority with add_to_front = true prepends within the low-priority group,
     * but remains below all medium and high priority filesystems.
     */
    public function test_low_priority_add_to_front_stays_below_medium_and_high_tiers(): void
    {
        $fs3Path = sys_get_temp_dir() . '/smol_fs_agg_test_3_' . uniqid();
        mkdir($fs3Path, 0777, true);
        $fs3 = new Filesystem($fs3Path);

        $this->fs1->file('theme.css', create: true)->write('Medium Default');
        $this->fs2->file('theme.css', create: true)->write('Low Base');
        $fs3->file('theme.css', create: true)->write('Low Prepended');

        // fs1 is Medium via constructor
        $aggregate = new AggregateFilesystem($this->fs1);

        $aggregate->addLowPriorityFilesystem($this->fs2);
        // Prepend within Low tier
        $aggregate->addLowPriorityFilesystem($fs3, add_to_front: true);

        // Medium tier still wins over prepended Low tier
        $this->assertEquals('Medium Default', $aggregate->file('theme.css')->read());

        // Remove Medium file -> Low Prepended (fs3) wins over Low Base (fs2)
        $this->fs1->file('theme.css')->delete();
        $this->assertEquals('Low Prepended', $aggregate->file('theme.css')->read());

        $this->removeDirectory($fs3Path);
    }

    /**
     * Test primary creation target resolves to the highest priority available root.
     */
    public function test_writes_target_highest_priority_root_for_new_files(): void
    {
        $fs3Path = sys_get_temp_dir() . '/smol_fs_agg_test_3_' . uniqid();
        mkdir($fs3Path, 0777, true);
        $fs3 = new Filesystem($fs3Path);

        $aggregate = new AggregateFilesystem();

        $aggregate->addLowPriorityFilesystem($this->fs1);
        $aggregate->addFilesystem($this->fs2); // Medium
        $aggregate->addHighPriorityFilesystem($fs3); // High

        // create: true targets highest-priority root ($fs3)
        $newFile = $aggregate->file('created.txt', create: true);
        $newFile->write('primary write');

        $this->assertTrue($fs3->file('created.txt')->exists());
        $this->assertNull($this->fs2->file('created.txt'));
        $this->assertNull($this->fs1->file('created.txt'));

        $this->removeDirectory($fs3Path);
    }

    /**
     * Test contains() verifies if an absolute path belongs to any contained filesystem root.
     */
    public function test_contains_checks_path_against_all_filesystems(): void
    {
        $aggregate = new AggregateFilesystem($this->fs1, $this->fs2);

        $fileInFs2 = $this->fs2->file('data.txt', create: true)->write('test');
        $absolutePathInFs2 = (string) $fileInFs2;

        $this->assertTrue($aggregate->contains($absolutePathInFs2));
        $this->assertFalse($aggregate->contains('/etc/passwd'));
    }

    /**
     * Test file() and directory() lookups with create: false and create: true.
     */
    public function test_file_and_directory_lookups(): void
    {
        $this->fs2->file('existing.txt', create: true)->write('hello');
        $this->fs2->directory('existing_dir', create: true)->create();

        $aggregate = new AggregateFilesystem($this->fs1, $this->fs2);

        // Scenario 1: create = false on existing items
        $file = $aggregate->file('existing.txt', create: false);
        $this->assertInstanceOf(AggregateFile::class, $file);
        $this->assertTrue($file->exists());

        $dir = $aggregate->directory('existing_dir', create: false);
        $this->assertInstanceOf(AggregateDirectory::class, $dir);
        $this->assertTrue($dir->exists());

        // Scenario 2: create = false on missing items returns null
        $this->assertNull($aggregate->file('missing.txt', create: false));
        $this->assertNull($aggregate->directory('missing_dir', create: false));

        // Scenario 3: create = true returns handle targeted at primary root ($fs1)
        $newFile = $aggregate->file('new.txt', create: true);
        $newFile->write('new file content');
        $this->assertTrue($this->fs1->file('new.txt')->exists());

        $newDir = $aggregate->directory('new_dir', create: true);
        $newDir->create();
        $this->assertTrue($this->fs1->directory('new_dir')->exists());
    }

    /**
     * Test files() and directories() aggregate items into 0-indexed arrays.
     */
    public function test_files_and_directories_return_zero_indexed_lists(): void
    {
        $this->fs1->file('a.txt', create: true)->write('a1');
        $this->fs2->file('a.txt', create: true)->write('a2');
        $this->fs2->file('b.txt', create: true)->write('b2');

        $this->fs1->directory('dirA', create: true)->create();
        $this->fs2->directory('dirA', create: true)->create();
        $this->fs2->directory('dirB', create: true)->create();

        $aggregate = new AggregateFilesystem($this->fs1, $this->fs2);

        // Files listing
        $files = $aggregate->files();
        $this->assertCount(2, $files);
        $this->assertArrayHasKey(0, $files); // Asserts 0-indexed
        $this->assertArrayHasKey(1, $files);

        $filenames = array_map(fn(AggregateFile $f) => $f->filename(), $files);
        $this->assertContains('a.txt', $filenames);
        $this->assertContains('b.txt', $filenames);

        // Directories listing
        $dirs = $aggregate->directories();
        $this->assertCount(2, $dirs);
        $this->assertArrayHasKey(0, $dirs); // Asserts 0-indexed
        $this->assertArrayHasKey(1, $dirs);

        $basenames = array_map(fn(AggregateDirectory $d) => $d->basename(), $dirs);
        $this->assertContains('dirA', $basenames);
        $this->assertContains('dirB', $basenames);
    }

    /**
     * Test globFile() and globDirectory() return the first match or null.
     */
    public function test_glob_helpers(): void
    {
        $this->fs2->file('config.json', create: true)->write('{}');
        $this->fs2->directory('cache_v1', create: true)->create();

        $aggregate = new AggregateFilesystem($this->fs1, $this->fs2);

        $fileMatch = $aggregate->globFile('*.json');
        $this->assertInstanceOf(AggregateFile::class, $fileMatch);
        $this->assertEquals('config.json', $fileMatch->filename());

        $dirMatch = $aggregate->globDirectory('cache_*');
        $this->assertInstanceOf(AggregateDirectory::class, $dirMatch);
        $this->assertEquals('cache_v1', $dirMatch->basename());

        $this->assertNull($aggregate->globFile('*.yaml'));
        $this->assertNull($aggregate->globDirectory('logs_*'));
    }

    /**
     * Test copyIn() imports external files into the aggregate filesystem.
     */
    public function test_copy_in_imports_external_file(): void
    {
        $tempSource = sys_get_temp_dir() . '/smol_fs_ext_' . uniqid() . '.txt';
        file_put_contents($tempSource, 'external data');

        $aggregate = new AggregateFilesystem($this->fs1, $this->fs2);

        // Destination target inside $fs1
        $destFile = $this->fs1->file('imported.txt', create: true);

        $aggregate->copyIn($tempSource, $destFile, allow_overwrite: true);

        $this->assertTrue($this->fs1->file('imported.txt')->exists());
        $this->assertEquals('external data', $this->fs1->file('imported.txt')->read());

        unlink($tempSource);
    }

    /**
     * Test copyOut() exports files from the aggregate filesystem.
     */
    public function test_copy_out_exports_file(): void
    {
        $this->fs2->file('export_me.txt', create: true)->write('export data');

        $aggregate = new AggregateFilesystem($this->fs1, $this->fs2);

        $sourceFile = $aggregate->file('export_me.txt');
        $tempDest = sys_get_temp_dir() . '/smol_fs_out_' . uniqid() . '.txt';

        $aggregate->copyOut($sourceFile, $tempDest, allow_overwrite: true);

        $this->assertFileExists($tempDest);
        $this->assertEquals('export data', file_get_contents($tempDest));

        unlink($tempDest);
    }

    /**
     * Test copy() duplicates files across aggregate filesystems.
     */
    public function test_copy_duplicates_file_within_aggregate(): void
    {
        $sourceFile = $this->fs2->file('original.txt', create: true)->write('source content');

        $aggregate = new AggregateFilesystem($this->fs1, $this->fs2);

        $destFile = $this->fs1->file('copy.txt', create: true);

        $aggregate->copy($sourceFile, $destFile, allow_overwrite: true);

        $this->assertTrue($this->fs1->file('copy.txt')->exists());
        $this->assertEquals('source content', $this->fs1->file('copy.txt')->read());
    }

    /**
     * Test move() transfers files within aggregate filesystems.
     */
    public function test_move_transfers_file_within_aggregate(): void
    {
        $sourceFile = $this->fs2->file('move_me.txt', create: true)->write('movable content');

        $aggregate = new AggregateFilesystem($this->fs1, $this->fs2);

        $destFile = $this->fs1->file('moved.txt', create: true);

        $aggregate->move($sourceFile, $destFile, allow_overwrite: true);

        $this->assertTrue($this->fs1->file('moved.txt')->exists());
        $this->assertNull($this->fs2->file('move_me.txt'));
    }

    /**
     * Test moveIn() imports external files into aggregate filesystem and removes source.
     */
    public function test_move_in_imports_and_deletes_external_source(): void
    {
        $tempSource = sys_get_temp_dir() . '/smol_fs_move_in_' . uniqid() . '.txt';
        file_put_contents($tempSource, 'move in data');

        $aggregate = new AggregateFilesystem($this->fs1, $this->fs2);

        $destFile = $this->fs1->file('imported_moved.txt', create: true);

        $aggregate->moveIn($tempSource, $destFile, allow_overwrite: true);

        $this->assertFileDoesNotExist($tempSource);
        $this->assertTrue($this->fs1->file('imported_moved.txt')->exists());
    }

    /**
     * Test moveOut() exports files from aggregate filesystem and removes source.
     */
    public function test_move_out_exports_and_removes_source(): void
    {
        $sourceFile = $this->fs2->file('move_out.txt', create: true)->write('move out content');

        $aggregate = new AggregateFilesystem($this->fs1, $this->fs2);

        $tempDest = sys_get_temp_dir() . '/smol_fs_moved_out_' . uniqid() . '.txt';

        $aggregate->moveOut($sourceFile, $tempDest, allow_overwrite: true);

        $this->assertFileExists($tempDest);
        $this->assertNull($this->fs2->file('move_out.txt'));

        unlink($tempDest);
    }

}
