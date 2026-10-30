<?php

namespace App\Support;

use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PdfDownload
{
    public function response(string $view, array $data, string $filename, bool $landscape = false): Response
    {
        $path = sys_get_temp_dir().'/'.uniqid('pdf_', true).'.pdf';

        try {
            $pdf = Pdf::view($view, $data)->format('a4')->margins(10, 10, 10, 10);
            if ($landscape) {
                $pdf->landscape();
            }
            $pdf->save($path);
            if (! is_file($path) || file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
                throw new \RuntimeException('PDF generation did not produce a valid PDF file.');
            }

            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'-'.now()->format('Ymd-Hi').'.pdf"',
            ])->deleteFileAfterSend();
        } catch (Throwable $exception) {
            if (is_file($path)) {
                @unlink($path);
            }
            report($exception);

            $message = 'PDF şu anda oluşturulamadı. Lütfen biraz sonra tekrar deneyin.';
            if (request()->expectsJson()) {
                return response()->json(['message' => $message], 503);
            }

            $route = request()->route();
            $sourceRoute = \Illuminate\Support\Str::beforeLast($route->getName(), '.export');
            $parameters = array_merge(request()->query(), $route->parameters());
            unset($parameters['type']);
            $returnUrl = route($sourceRoute, $parameters);

            return response()->view('errors.pdf-unavailable', ['message' => $message, 'returnUrl' => $returnUrl], 503)
                ->header('Cache-Control', 'no-store');
        }
    }
}
