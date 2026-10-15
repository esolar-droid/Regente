<?php
/**
 * ReportController — placeholder digno mientras se define el módulo de Reportes.
 *
 * Evita el error 500 por controlador inexistente. Las rutas de exportación
 * (/reports/export/csv, /reports/export/pdf) devuelven un aviso claro; serán
 * implementadas cuando se especifique el apartado de reportes.
 */

namespace App\Controllers;

class ReportController extends Controller
{
    public function index()
    {
        $this->requireAuth();
        $this->view('reports/index', []);
    }

    public function exportCsv()
    {
        $this->requireAuth();
        http_response_code(501);
        header('Content-Type: text/plain; charset=utf-8');
        echo "La exportación CSV formará parte del módulo de Reportes (en diseño).";
    }

    public function exportPdf()
    {
        $this->requireAuth();
        http_response_code(501);
        header('Content-Type: text/plain; charset=utf-8');
        echo "La exportación PDF formará parte del módulo de Reportes (en diseño).";
    }
}
