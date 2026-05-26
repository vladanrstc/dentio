<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientPortal\AcceptPatientPortalInviteRequest;
use App\Http\Requests\PatientPortal\SendPatientPortalInviteRequest;
use App\Http\Resources\PatientPortalInviteResource;
use App\Services\Contracts\PatientPortalInviteServiceInterface;
use App\Services\RecaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PatientPortalInviteApiController extends Controller
{
    public function __construct(
        private readonly PatientPortalInviteServiceInterface $patientPortalInvites,
        private readonly RecaptchaService $recaptcha,
    ) {}

    public function store(SendPatientPortalInviteRequest $request): JsonResponse
    {
        $invite = $this->patientPortalInvites->send(
            $request->user(),
            $request->validated('email'),
        );

        return (new PatientPortalInviteResource($invite))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $token): PatientPortalInviteResource
    {
        $invite = $this->patientPortalInvites->findByToken($token);
        abort_if($invite === null, Response::HTTP_NOT_FOUND, __('errors.invite_not_found'));

        return new PatientPortalInviteResource($invite);
    }

    public function accept(AcceptPatientPortalInviteRequest $request, string $token): JsonResponse
    {
        $this->recaptcha->verify($request->validated('recaptcha_token'));

        $patient = $this->patientPortalInvites->accept(
            $token,
            $request->validated('password'),
        );

        return response()->json([
            'data' => [
                'patient' => [
                    'id' => $patient->id,
                    'full_name' => $patient->fullName(),
                    'first_name' => $patient->first_name,
                    'last_name' => $patient->last_name,
                    'email' => $patient->email,
                ],
            ],
        ]);
    }
}
