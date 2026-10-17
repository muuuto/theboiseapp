<?php

namespace Tests\Feature;

use App\Console\Mail\BackupMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private string $backupPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupPath = storage_path('framework/testing/backups');
        File::deleteDirectory($this->backupPath);
        config(['backup.path' => $this->backupPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupPath);

        parent::tearDown();
    }

    public function test_old_open_routes_are_gone(): void
    {
        $this->get('/clear-cache')->assertNotFound();
        $this->get('/test')->assertNotFound();
    }

    public function test_only_admins_can_use_maintenance(): void
    {
        $this->get('/admin/maintenance')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/maintenance')->assertForbidden();
        $this->actingAs($user)->post('/admin/maintenance/clear-cache')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/maintenance')->assertOk();
    }

    public function test_unknown_actions_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/maintenance/db:wipe')
            ->assertNotFound();
    }

    public function test_admin_can_run_a_backup_and_download_it(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/maintenance/backup')
            ->assertRedirect('/admin/maintenance')
            ->assertSessionHas('output', fn ($output) => str_contains($output, 'Backup written'));

        $files = glob($this->backupPath . '/theboise-*.sql.gz');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('CREATE TABLE `users`', gzdecode(file_get_contents($files[0])));

        $this->actingAs($admin)->get('/admin/maintenance/backups/' . basename($files[0]))->assertOk();
        $this->actingAs($admin)->get('/admin/maintenance/backups/..%2F..%2F.env')->assertNotFound();
    }

    public function test_backup_keeps_only_the_newest_files(): void
    {
        config(['backup.keep' => 2]);
        File::ensureDirectoryExists($this->backupPath);
        foreach (['2020-01-01_000000', '2020-01-02_000000', '2020-01-03_000000'] as $date) {
            touch($this->backupPath . "/theboise-{$date}-aaaaaaaaaaaaaaaa.sql.gz");
        }

        $this->artisan('backup:database')->assertSuccessful();

        $files = array_map('basename', glob($this->backupPath . '/theboise-*.sql.gz'));
        $this->assertCount(2, $files);
        $this->assertNotContains('theboise-2020-01-02_000000-aaaaaaaaaaaaaaaa.sql.gz', $files);
        $this->assertFileExists($this->backupPath . '/.htaccess');
    }

    public function test_backup_can_be_emailed(): void
    {
        Mail::fake();
        config(['backup.mail_to' => 'admin@example.com']);

        $this->artisan('backup:database --mail')->assertSuccessful();

        Mail::assertSent(BackupMail::class, fn ($mail) => $mail->hasTo('admin@example.com'));
    }
}
