<?php

namespace App\Swagger\Admin;

/**
 * @OA\Tag(
 *     name="Admin - Employees",
 *     description="Gestão de funcionários da empresa (CRUD)"
 * )
 */
class Employee {}



/**
 * ==========================================
 * Listar funcionários (INDEX)
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/admin/employees",
 *     summary="Lista todos os funcionários da empresa",
 *     description="Retorna uma lista paginada contendo todos os funcionários associados à empresa do administrador.",
 *     tags={"Admin - Employees"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         required=false,
 *         description="Página atual",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada de funcionários",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=20),
 *             @OA\Property(property="total", type="integer", example=45),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="integer", example=10),
 *                     @OA\Property(property="company_id", type="integer", example=3),
 *                     @OA\Property(property="name", type="string", example="Maria Santos"),
 *                     @OA\Property(property="email", type="string", example="maria@empresa.com"),
 *                     @OA\Property(property="created_at", type="string", example="2025-02-10T14:32:20Z")
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class EmployeeIndex {}



/**
 * ==========================================
 * Criar funcionário (STORE)
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/admin/employees",
 *     summary="Cria um novo funcionário para a empresa",
 *     description="Apenas administradores podem criar novos funcionários.",
 *     tags={"Admin - Employees"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         description="Dados do funcionário",
 *         @OA\JsonContent(
 *             required={"name","email","password"},
 *             @OA\Property(property="name", type="string", example="João Silva"),
 *             @OA\Property(property="email", type="string", example="joao@empresa.com"),
 *             @OA\Property(property="password", type="string", example="12345678"),
 *             @OA\Property(property="role", type="string", nullable=true, enum={"admin","manager","area_manager","employee"}, example="employee")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Funcionário criado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", example=15),
 *             @OA\Property(property="company_id", type="integer", example=3),
 *             @OA\Property(property="name", type="string", example="João Silva"),
 *             @OA\Property(property="email", type="string", example="joao@empresa.com")
 *         )
 *     )
 * )
 */
class EmployeeStore {}



/**
 * ==========================================
 * Mostrar funcionário (SHOW)
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/admin/employees/{id}",
 *     summary="Exibe detalhes de um funcionário",
 *     description="Retorna os dados completos de um funcionário específico.",
 *     tags={"Admin - Employees"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID do funcionário",
 *         @OA\Schema(type="integer", example=10)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Detalhes do funcionário",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", example=10),
 *             @OA\Property(property="company_id", type="integer", example=3),
 *             @OA\Property(property="name", type="string", example="Maria Santos"),
 *             @OA\Property(property="email", type="string", example="maria@empresa.com"),
 *             @OA\Property(property="created_at", type="string", example="2025-02-10T14:32:20Z")
 *         )
 *     ),
 *
 *     @OA\Response(response=404, description="Funcionário não encontrado")
 * )
 */
class EmployeeShow {}



/**
 * ==========================================
 * Atualizar funcionário (UPDATE)
 * ==========================================
 *
 * @OA\Put(
 *     path="/v1/admin/employees/{id}",
 *     summary="Atualiza os dados de um funcionário",
 *     description="Permite alterar nome, email e opcionalmente a senha.",
 *     tags={"Admin - Employees"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID do funcionário",
 *         @OA\Schema(type="integer", example=10)
 *     ),
 *
 *     @OA\RequestBody(
 *         required=false,
 *         description="Dados a serem atualizados",
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", example="Maria Silva"),
 *             @OA\Property(property="email", type="string", example="maria.silva@empresa.com"),
 *             @OA\Property(property="password", type="string", example="novaSenha123")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Funcionário atualizado",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", example=10),
 *             @OA\Property(property="name", type="string", example="Maria Silva"),
 *             @OA\Property(property="email", type="string", example="maria.silva@empresa.com")
 *         )
 *     )
 * )
 */
class EmployeeUpdate {}



/**
 * ==========================================
 * Deletar funcionário (DESTROY)
 * ==========================================
 *
 * @OA\Delete(
 *     path="/v1/admin/employees/{id}",
 *     summary="Remove um funcionário da empresa",
 *     description="Remove o registro permanentemente.",
 *     tags={"Admin - Employees"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID do funcionário",
 *         @OA\Schema(type="integer", example=10)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Funcionário removido",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Deletado")
 *         )
 *     ),
 *
 *     @OA\Response(response=404, description="Funcionário não encontrado")
 * )
 */
class EmployeeDestroy {}
