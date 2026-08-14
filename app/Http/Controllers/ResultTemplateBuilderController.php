<?php

namespace App\Http\Controllers;

use App\Models\ResultTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ResultTemplateBuilderController extends Controller
{
    protected function schoolId(): ?int
    {
        $user = Auth::user();

        // Adapt to your STRIDE auth/school model (e.g. $user->school_id
        // or $user->school->id, or a current-school session helper).
        return $user->school_id ?? $user->school?->id ?? null;
    }

    public function index(): View
    {
        $templates = ResultTemplate::forSchool($this->schoolId())
            ->orderBy('updated_at', 'desc')
            ->paginate(15);

        return view('builder.templates-index', ['templates' => $templates]);
    }

    public function create(): View
    {
        return view('builder.result-template', [
            'template'          => null,
            'availableVariables' => ResultTemplate::availableVariables(),
            'paperSizes'        => ['A4' => 'A4', 'A5' => 'A5', 'Legal' => 'Legal', 'Letter' => 'Letter'],
            'orientations'      => ['portrait' => 'Portrait', 'landscape' => 'Landscape'],
        ]);
    }

    public function edit(ResultTemplate $resultTemplate): View
    {
        abort_unless($resultTemplate->school_id === $this->schoolId(), 403);

        return view('builder.result-template', [
            'template'           => $resultTemplate,
            'availableVariables' => ResultTemplate::availableVariables(),
            'paperSizes'         => ['A4' => 'A4', 'A5' => 'A5', 'Legal' => 'Legal', 'Letter' => 'Letter'],
            'orientations'       => ['portrait' => 'Portrait', 'landscape' => 'Landscape'],
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validatedPayload($request);

        $template = ResultTemplate::create(array_merge($data, [
            'school_id' => $this->schoolId(),
        ]));

        return $this->respond($request, $template);
    }

    public function update(Request $request, ResultTemplate $resultTemplate): JsonResponse|RedirectResponse
    {
        abort_unless($resultTemplate->school_id === $this->schoolId(), 403);

        $resultTemplate->update($this->validatedPayload($request));

        return $this->respond($request, $resultTemplate);
    }

    public function destroy(ResultTemplate $resultTemplate): RedirectResponse
    {
        abort_unless($resultTemplate->school_id === $this->schoolId(), 403);

        $resultTemplate->delete();

        return redirect()
            ->route('result-templates.index')
            ->with('status', 'Template deleted.');
    }

    protected function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'grapes_json'   => ['nullable', 'string'],
            'compiled_html' => ['nullable', 'string'],
            'compiled_css'  => ['nullable', 'string'],
            'paper_size'    => ['sometimes', 'string', 'in:A4,A5,Legal,Letter'],
            'orientation'   => ['sometimes', 'string', 'in:portrait,landscape'],
            'is_default'    => ['sometimes', 'boolean'],
        ]);

        $validated['paper_size']  = $request->input('paper_size', 'A4');
        $validated['orientation'] = $request->input('orientation', 'portrait');

        return $validated;
    }

    protected function respond(Request $request, ResultTemplate $template): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->isJson()) {
            return response()->json([
                'ok'       => true,
                'template' => $template->only(['id', 'name', 'paper_size', 'orientation', 'updated_at']),
            ]);
        }

        return redirect()
            ->route('result-templates.edit', $template)
            ->with('status', 'Template saved.');
    }
}
