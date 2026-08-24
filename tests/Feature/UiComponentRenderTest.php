<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Regression guard untuk komponen UI baru (x-modal-crud, x-flash, SimpleCrud)
 * yang ditambahkan di Fase 2-3 — memastikan atribut aksesibilitas & struktur
 * tetap ada saat halaman dirender.
 */
class UiComponentRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->actingAs($user);

        return $user;
    }

    public function test_modal_crud_renders_accessible_dialog_attributes(): void
    {
        $this->admin();

        Volt::test('pages.units.index')
            ->assertOk()
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml("\$wire.entangle('showModal')");
    }

    public function test_flash_component_renders_status_and_alert_roles(): void
    {
        $this->admin();

        session()->flash('status', 'Unit berhasil ditambahkan.');
        session()->flash('error', 'Terjadi kesalahan.');

        Volt::test('pages.units.index')
            ->assertSee('Unit berhasil ditambahkan.')
            ->assertSee('Terjadi kesalahan.')
            ->assertSeeHtml('role="status"')
            ->assertSeeHtml('role="alert"');
    }

    public function test_all_simple_crud_master_pages_render_ok(): void
    {
        $this->admin();

        foreach ([
            'pages.units.index',
            'pages.employee-statuses.index',
            'pages.golongans.index',
            'pages.jabatan-fungsionals.index',
            'pages.banks.index',
            'pages.deduction-types.index',
        ] as $page) {
            Volt::test($page)->assertOk();
        }
    }
}
