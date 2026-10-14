<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Evento;
use App\Models\FormType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-v (última de la Fase 1e) —
 * Carga masiva de inscripciones por CSV. Mismo criterio de verificación
 * que fases anteriores: solo el wiring del panel (parseo de CSV, rutas,
 * sesión/rol, delegación in-process), no la lógica de negocio de
 * `importarBulk()` en sí — esa ya está cubierta a fondo en
 * ApiRestEvent/tests/Feature/RegistroManualBulkTest.php (incluido el
 * soporte de talleres, traído a este worktree por cherry-pick).
 */
class RegistroManualTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;
    private FormType $formType;
    private Category $categoria;
    private array $superSession;
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->evento = Evento::factory()->create();
        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '5K', 'price' => 50]);
        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => true, 'has_team' => false]);

        $super = AdminUser::create([
            'nombre' => 'Super', 'email' => 'super@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'super_admin',
            'activo' => true, 'evento_id' => null,
        ]);
        $this->superSession = [
            'admin_token' => $super->createToken('t')->plainTextToken,
            'admin_user' => ['id' => $super->id, 'rol' => 'super_admin'],
        ];

        $admin = AdminUser::create([
            'nombre' => 'Admin scoped', 'email' => 'admin@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'admin',
            'activo' => true, 'evento_id' => $this->evento->id,
        ]);
        $this->adminSession = [
            'admin_token' => $admin->createToken('t')->plainTextToken,
            'admin_user' => ['id' => $admin->id, 'rol' => 'admin', 'evento_id' => $this->evento->id, 'eventoIds' => [$this->evento->id]],
        ];
    }

    private function csvContent(): string
    {
        $filas = [
            ['numero_documento', 'tipo_documento', 'nombre', 'apellido', 'alias', 'genero', 'fecha_nacimiento', 'email', 'direccion', 'ciudad', 'telefono', 'contacto_emergencia_nombre', 'contacto_emergencia_telefono', 'contacto_emergencia_relacion', 'talleres'],
            ['12345678', 'DNI', 'Ana', 'Prueba', '', 'Femenino', '1995-06-15', 'ana@example.net', 'Calle Falsa 123', 'La Paz', '77712345', 'Juan Prueba', '77798765', 'Padre', ''],
        ];

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        foreach ($filas as $fila) {
            fputcsv($handle, $fila);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function test_super_admin_ve_el_index(): void
    {
        $this->withSession($this->superSession)
            ->get("/admin/eventos/{$this->evento->id}/registro-manual")
            ->assertOk();
    }

    public function test_admin_no_super_admin_no_puede_ver_registro_manual(): void
    {
        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/registro-manual")
            ->assertForbidden();
    }

    public function test_plantilla_descarga_csv(): void
    {
        $response = $this->withSession($this->superSession)
            ->get("/admin/eventos/{$this->evento->id}/registro-manual/plantilla");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_store_crea_inscripcion_desde_el_csv(): void
    {
        $csv = UploadedFile::fake()->createWithContent('plantilla.csv', $this->csvContent());

        $response = $this->withSession($this->superSession)
            ->post("/admin/eventos/{$this->evento->id}/registro-manual", [
                'form_types_id' => $this->formType->id,
                'categoria' => '5K',
                'csv' => $csv,
            ]);

        $response->assertRedirect(route('admin.registro-manual.index', $this->evento->id));
        $response->assertSessionHas('registroManualReporte');

        $this->assertDatabaseHas('participantes', [
            'numero_documento' => '12345678',
            'nombre' => 'Ana',
            'apellido' => 'Prueba',
        ]);
        $this->assertDatabaseHas('registrations', [
            'evento_id' => $this->evento->id,
            'pago_status' => 'pending',
        ]);
    }

    public function test_store_sin_columnas_requeridas_rechaza(): void
    {
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['numero_documento', 'nombre']);
        fputcsv($handle, ['12345678', 'Ana']);
        rewind($handle);
        $csvIncompleto = stream_get_contents($handle);
        fclose($handle);

        $csv = UploadedFile::fake()->createWithContent('incompleto.csv', $csvIncompleto);

        $response = $this->withSession($this->superSession)
            ->post("/admin/eventos/{$this->evento->id}/registro-manual", [
                'form_types_id' => $this->formType->id,
                'categoria' => '5K',
                'csv' => $csv,
            ]);

        $response->assertSessionHasErrors('general');
        $this->assertDatabaseMissing('participantes', ['numero_documento' => '12345678']);
    }

    /**
     * Recuperación Windows-1252 (07/10/2026) — una celda con caracteres
     * que no son UTF-8 (típico al guardar desde Excel en español como
     * "CSV" en vez de "CSV UTF-8") ya no tumba el archivo completo.
     */
    public function test_un_campo_con_acentos_en_windows_1252_se_corrige_solo_y_no_tumba_el_archivo(): void
    {
        // "AMÉRICAS" con la É codificada en Windows-1252 (0xC9), no en UTF-8.
        $nombreRoto = "AM" . chr(0xC9) . "RICAS";
        $this->assertFalse(mb_check_encoding($nombreRoto, 'UTF-8'), 'el byte de prueba debe ser inválido en UTF-8');

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['numero_documento', 'tipo_documento', 'nombre', 'apellido', 'alias', 'genero', 'fecha_nacimiento', 'email', 'direccion', 'ciudad', 'telefono', 'contacto_emergencia_nombre', 'contacto_emergencia_telefono', 'contacto_emergencia_relacion', 'talleres']);
        fputcsv($handle, ['12345678', 'DNI', $nombreRoto, 'Prueba', '', 'Femenino', '1995-06-15', 'ana@example.net', 'Calle Falsa 123', 'La Paz', '77712345', 'Juan Prueba', '77798765', 'Padre', '']);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $archivo = UploadedFile::fake()->createWithContent('lote.csv', $csv);

        $response = $this->withSession($this->superSession)
            ->post("/admin/eventos/{$this->evento->id}/registro-manual", [
                'form_types_id' => $this->formType->id,
                'categoria' => '5K',
                'csv' => $archivo,
            ]);

        $response->assertRedirect(route('admin.registro-manual.index', $this->evento->id));
        $reporte = $response->getSession()->get('registroManualReporte');
        $this->assertCount(1, $reporte['creados']);
        $this->assertSame(2, $reporte['creados'][0]['fila']);
        $this->assertSame([], $reporte['errores']);

        $this->assertDatabaseHas('participantes', ['nombre' => 'AMÉRICAS']);
    }

    /** Un form_type sin categoría (es_staff) no exige el <select> de categoría. */
    public function test_store_sin_categoria_para_form_type_que_no_la_requiere(): void
    {
        $formTypeSinCategoria = FormType::factory()->create([
            'event_id' => $this->evento->id, 'has_team' => false, 'es_staff' => true,
        ]);
        $csv = UploadedFile::fake()->createWithContent('plantilla.csv', $this->csvContent());

        $response = $this->withSession($this->superSession)
            ->post("/admin/eventos/{$this->evento->id}/registro-manual", [
                'form_types_id' => $formTypeSinCategoria->id,
                'csv' => $csv,
            ]);

        $response->assertRedirect(route('admin.registro-manual.index', $this->evento->id));
        $this->assertDatabaseHas('participantes', [
            'numero_documento' => '12345678', 'categoria' => $formTypeSinCategoria->name,
        ]);
    }
}
