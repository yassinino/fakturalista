<?php

namespace App\Http\Controllers;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\Mail\ContactMessage;
use App\Mail\FreeTrialRequest;

class HomeController extends Controller
{

    public function index()
    {
        return view('index');
    }

    public function contact()
    {
        return view('contact');
    }

    public function freeTrial()
    {
        return view('free-trial');
    }

    public function pricing()
    {
        return view('pricing');
    }

    public function sendContact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:150',
            'content' => 'required|string|max:2000',
            'recaptcha_response' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'error' => 1,
                    'message' => 'Por favor revisa los campos e intenta de nuevo.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();
        $subject = $validated['subject'] ?: 'Nuevo mensaje de contacto';

        try {
            Mail::to('contact@aittouijardev.com')->send(new ContactMessage([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'subject' => $subject,
                'content' => $validated['content'],
                'locale' => app()->getLocale(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]));
        } catch (\Throwable $e) {
            Log::error('Contact email send failed', [
                'error' => $e->getMessage(),
                'email' => $validated['email'],
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'error' => 1,
                    'message' => 'No se pudo enviar el mensaje. Intenta de nuevo.',
                ], 500);
            }

            return back()->withErrors([
                'email' => 'No se pudo enviar el mensaje. Intenta de nuevo.',
            ])->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'error' => 0,
                'message' => 'Mensaje enviado correctamente.',
            ]);
        }

        return back()->with('status', 'Mensaje enviado correctamente.');
    }

    public function sendFreeTrial(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255',
            'company' => 'required|string|max:150',
            'recaptcha_response' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'error' => 1,
                    'message' => 'Por favor revisa los campos e intenta de nuevo.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        try {
            Mail::to('contact@aittouijardev.com')->send(new FreeTrialRequest([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'company' => $validated['company'],
                'locale' => app()->getLocale(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]));
        } catch (\Throwable $e) {
            Log::error('Free trial email send failed', [
                'error' => $e->getMessage(),
                'email' => $validated['email'],
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'error' => 1,
                    'message' => 'No se pudo enviar la solicitud. Intenta de nuevo.',
                ], 500);
            }

            return back()->withErrors([
                'email' => 'No se pudo enviar la solicitud. Intenta de nuevo.',
            ])->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'error' => 0,
                'message' => 'Solicitud enviada correctamente.',
            ]);
        }

        return back()->with('status', 'Solicitud enviada correctamente.');
    }

    public function about()
    {
        return view('about');
    }

    public function setLocale(Request $request)
    {
        $supported = config('app.supported_locales', ['es', 'fr', 'en']);
        $locale = $request->input('locale');

        if ($locale && in_array($locale, $supported, true)) {
            $request->session()->put('locale', $locale);
        }

        return back();
    }

    public function success(Request $request)
    {
        return view('subscription.success', [
            'session_id' => $request->get('session_id')
        ]);
    }

    public function cancel()
    {
        return view('subscription.cancel');
    }
}
