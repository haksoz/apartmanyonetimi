<?php

namespace App\Http\Controllers;

use App\Support\Paytr\CallbackRejected;
use App\Support\Paytr\HandleNotification;
use App\Support\Paytr\VerifiedNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class PaytrCallbackController extends Controller
{
    public function __invoke(Request $request, HandleNotification $handler): Response
    {
        try {
            // POST body only: query-string values and browser session state are not payment evidence.
            $notification = VerifiedNotification::verify($request->request->all());
            $accepted = $handler->handle($notification);

            return $this->plain($accepted ? 'OK' : 'Payment requires review.', $accepted ? 200 : 409);
        } catch (CallbackRejected $exception) {
            return $this->plain($exception->getMessage(), $exception->httpStatus);
        } catch (Throwable $exception) {
            report($exception);

            return $this->plain('Payment notification temporarily unavailable.', 503);
        }
    }

    private function plain(string $body, int $status): Response
    {
        return response($body, $status, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
