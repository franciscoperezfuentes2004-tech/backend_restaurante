<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class KpiController extends ReportController
{
    /**
     * Reutiliza o expone métricas de rendimiento para KPIs de delivery.
     */
    public function kpisRendimiento(Request $request)
    {
        return parent::kpisRendimiento($request);
    }

    public function metricasRendimiento(Request $request)
    {
        return parent::metricasRendimiento($request);
    }
}
