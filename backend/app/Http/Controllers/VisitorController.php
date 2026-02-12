<?php

namespace App\Http\Controllers;

use App\Enums\EnumDocumentType;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class VisitorController extends Controller
{
    // Listar todos los visitantes (con paginación para evitar OOM)
    public function index()
    {
        return Visitor::paginate(50);
    }

    // Crear un nuevo visitante
    public function store(Request $request)
    {
        $documentType = (int) $request->input('document_type', 0);

        $rules = [
            'identity_document' => [
                $documentType === 3 ? 'nullable' : 'required',
                'string',
                //  FIX: Solo validar unique si NO es tipo 3 Y tiene valor
                Rule::unique('visitors', 'identity_document')
                    ->when($documentType === 3 || empty($request->identity_document), function ($rule) {
                        return $rule->whereNotNull('identity_document');
                    })
            ],
            'document_type' => 'required|integer|in:' . implode(',', EnumDocumentType::getValues()),
            'name' => 'required|string',
            'lastName' => 'required|string',
            'phone' => 'nullable|string|regex:/^\d{3}-\d{3}-\d{4}$/',
            'email' => 'nullable|email',
            'institution' => 'nullable|string|max:255'
        ];

        $messages = [
            'identity_document.required' => 'El documento de identidad es obligatorio para este tipo de identificación.',
            'identity_document.unique' => 'El documento de identidad ya está registrado. Seleccione el visitante existente o búsquelo para asociarlo.',
            'document_type.required' => 'El tipo de documento es obligatorio.',
            'document_type.in' => 'El tipo de documento seleccionado no es válido.',
            'name.required' => 'El nombre es obligatorio.',
            'lastName.required' => 'El apellido es obligatorio.',
            'phone.regex' => 'El teléfono debe tener el formato 000-000-0000.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'institution.max' => 'La institución no puede exceder 255 caracteres.'
        ];

        $attributes = [
            'identity_document' => 'documento de identidad',
            'document_type' => 'tipo de documento',
            'name' => 'nombre',
            'lastName' => 'apellido',
            'phone' => 'teléfono',
            'email' => 'correo electrónico',
            'institution' => 'institución'
        ];

        $validated = $request->validate($rules, $messages, $attributes);

        //  FIX: Si es tipo 3 (Sin Identificación), identity_document debe ser null
        if ($documentType === 3) {
            $validated['identity_document'] = null;
        }

        // Asociar el usuario autenticado si existe
        $validated['user_id'] = $request->user()?->id;

        try {
            $visitor = DB::transaction(function () use ($validated) {
                return Visitor::create($validated);
            });

            return response()->json($visitor, 201);
        } catch (\Exception $e) {
            Log::error('Error al crear visitante', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al crear el visitante: ' . $e->getMessage()
            ], 500);
        }
    }

    // Ver un visitante por ID
    public function show($id)
    {
        try {
            $visitor = Visitor::findOrFail($id);
            return response()->json($visitor);
        } catch (\Exception $e) {
            Log::error('Error al obtener visitante', [
                'visitor_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Visitante no encontrado'
            ], 404);
        }
    }

    // Actualizar visitante
    public function update(Request $request, $id)
    {
        try {
            $visitor = Visitor::findOrFail($id);
            $documentType = (int) $request->input('document_type', $visitor->document_type ?? 1);

            //  LOG para debugging
            Log::info('Actualizando visitante', [
                'visitor_id' => $id,
                'document_type' => $documentType,
                'identity_document' => $request->identity_document,
                'request_data' => $request->all()
            ]);

            $rules = [
                'identity_document' => [
                    $documentType === 3 ? 'nullable' : 'required',
                    'string',
                    //  FIX CRÍTICO: Validación de unique más robusta
                    Rule::unique('visitors', 'identity_document')
                        ->ignore($visitor->id)
                        ->when($documentType === 3 || empty($request->identity_document), function ($rule) {
                            // Si es tipo 3 o vacío, solo verificar contra docs NO nulos
                            return $rule->whereNotNull('identity_document');
                        })
                ],
                'document_type' => 'required|integer|in:' . implode(',', EnumDocumentType::getValues()),
                'name' => 'required|string',
                'lastName' => 'required|string',
                'phone' => 'nullable|string|regex:/^\d{3}-\d{3}-\d{4}$/',
                'email' => 'nullable|email',
                'institution' => 'nullable|string|max:255'
            ];

            $messages = [
                'identity_document.required' => 'El documento de identidad es obligatorio para este tipo de identificación.',
                'identity_document.unique' => 'Ya existe otro visitante con ese documento de identidad.',
                'document_type.required' => 'El tipo de documento es obligatorio.',
                'document_type.in' => 'El tipo de documento seleccionado no es válido.',
                'name.required' => 'El nombre es obligatorio.',
                'lastName.required' => 'El apellido es obligatorio.',
                'phone.regex' => 'El teléfono debe tener el formato 000-000-0000.',
                'email.email' => 'El correo electrónico no tiene un formato válido.',
                'institution.max' => 'La institución no puede exceder 255 caracteres.'
            ];

            $attributes = [
                'identity_document' => 'documento de identidad',
                'document_type' => 'tipo de documento',
                'name' => 'nombre',
                'lastName' => 'apellido',
                'phone' => 'teléfono',
                'email' => 'correo electrónico',
                'institution' => 'institución'
            ];

            $validated = $request->validate($rules, $messages, $attributes);

            //  FIX: Si es tipo 3 (Sin Identificación), identity_document debe ser null
            if ($documentType === 3) {
                $validated['identity_document'] = null;
            }

            DB::transaction(function () use ($visitor, $validated) {
                $visitor->update($validated);
            });

            Log::info('Visitante actualizado exitosamente', [
                'visitor_id' => $visitor->id
            ]);

            return response()->json($visitor);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Errores de validación (422)
            Log::warning('Validación fallida al actualizar visitante', [
                'visitor_id' => $id,
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Visitante no encontrado (404)
            Log::warning('Visitante no encontrado', [
                'visitor_id' => $id
            ]);
            
            return response()->json([
                'message' => 'Visitante no encontrado'
            ], 404);

        } catch (\Exception $e) {
            // Error general del servidor (500)
            Log::error('Error al actualizar visitante', [
                'visitor_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error al actualizar el visitante: ' . $e->getMessage()
            ], 500);
        }
    }

    // Eliminar visitante
    public function destroy($id)
    {
        try {
            $visitor = Visitor::findOrFail($id);
            
            // Verificar si tiene visitas activas
            if ($visitor->visits()->where('status_id', 1)->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar un visitante con visitas activas'
                ], 422);
            }
            
            $visitor->delete();

            Log::info('Visitante eliminado', [
                'visitor_id' => $id
            ]);

            return response()->json([
                'message' => 'Visitante eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar visitante', [
                'visitor_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Error al eliminar el visitante'
            ], 500);
        }
    }

    // Buscar visitante por documento
    public function search($identity_document)
    {
        try {
            $visitor = Visitor::where('identity_document', $identity_document)->first();

            if (!$visitor) {
                return response()->json([
                    'message' => 'Visitante no encontrado.'
                ], 204);
            }

            return response()->json($visitor);

        } catch (\Exception $e) {
            Log::error('Error al buscar visitante', [
                'identity_document' => $identity_document,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Error al buscar el visitante'
            ], 500);
        }
    }
}
