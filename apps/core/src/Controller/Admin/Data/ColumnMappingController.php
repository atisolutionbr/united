<?php

namespace App\Controller\Admin\Data;

use App\Entity\Data\DatasetColumnMapping;
use App\Entity\ProviderPackage;
use App\Entity\User;
use App\Repository\Data\DatasetColumnMappingRepository;
use App\Repository\ProviderPackageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/data-pipeline/mapping')]
#[IsGranted(User::ROLE_ADMIN)]
final class ColumnMappingController extends AbstractController
{
    #[Route('', name: 'app_admin_column_mapping', methods: ['GET'])]
    public function index(ProviderPackageRepository $packageRepository): Response
    {
        $packages = $packageRepository->findBy(['isMonitored' => true], ['updatedAt' => 'DESC']);

        return $this->render('admin/data_pipeline/column_mapping_index.html.twig', [
            'packages' => $packages,
        ]);
    }

    #[Route('/package/{id<\d+>}', name: 'app_admin_column_mapping_package', methods: ['GET'])]
    public function packageMappings(ProviderPackage $package, DatasetColumnMappingRepository $repository): Response
    {
        $mappings = $repository->findByPackageOrdered($package);

        return $this->render('admin/data_pipeline/column_mapping_package.html.twig', [
            'package' => $package,
            'mappings' => $mappings,
            'availableTypes' => DatasetColumnMapping::getAvailableTypes(),
            'availableRules' => DatasetColumnMapping::getAvailableRules(),
        ]);
    }

    #[Route('/package/{id<\d+>}/new', name: 'app_admin_column_mapping_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ProviderPackage $package, EntityManagerInterface $entityManager): Response
    {
        $mapping = (new DatasetColumnMapping())
            ->setProviderPackage($package)
            ->setTargetDataType(DatasetColumnMapping::TYPE_STRING)
            ->setIsActive(true)
        ;

        if ($request->isMethod('POST')) {
            $mapping = $this->bindFromRequest($mapping, $request);
            $errors = $this->validateMapping($mapping);

            if (empty($errors)) {
                $entityManager->persist($mapping);
                $entityManager->flush();
                $this->addFlash('success', sprintf('Mapeamento da coluna "%s" criado com sucesso.', $mapping->getOriginalColumn()));

                return $this->redirectToRoute('app_admin_column_mapping_package', ['id' => $package->getId()]);
            }

            foreach ($errors as $error) {
                $this->addFlash('danger', $error);
            }
        }

        return $this->render('admin/data_pipeline/column_mapping_form.html.twig', [
            'mapping' => $mapping,
            'package' => $package,
            'isEdit' => false,
            'availableTypes' => DatasetColumnMapping::getAvailableTypes(),
            'availableRules' => DatasetColumnMapping::getAvailableRules(),
            'typeLabelMap' => $this->buildTypeLabelMap(),
            'ruleLabelMap' => $this->buildRuleLabelMap(),
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'app_admin_column_mapping_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DatasetColumnMapping $mapping, EntityManagerInterface $entityManager): Response
    {
        $package = $mapping->getProviderPackage();

        if ($request->isMethod('POST')) {
            $mapping = $this->bindFromRequest($mapping, $request);
            $errors = $this->validateMapping($mapping);

            if (empty($errors)) {
                $entityManager->flush();
                $this->addFlash('success', 'Mapeamento atualizado com sucesso.');

                return $this->redirectToRoute('app_admin_column_mapping_package', ['id' => $package->getId()]);
            }

            foreach ($errors as $error) {
                $this->addFlash('danger', $error);
            }
        }

        return $this->render('admin/data_pipeline/column_mapping_form.html.twig', [
            'mapping' => $mapping,
            'package' => $package,
            'isEdit' => true,
            'availableTypes' => DatasetColumnMapping::getAvailableTypes(),
            'availableRules' => DatasetColumnMapping::getAvailableRules(),
            'typeLabelMap' => $this->buildTypeLabelMap(),
            'ruleLabelMap' => $this->buildRuleLabelMap(),
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'app_admin_column_mapping_delete', methods: ['POST'])]
    public function delete(DatasetColumnMapping $mapping, EntityManagerInterface $entityManager): Response
    {
        $packageId = $mapping->getProviderPackage()->getId();
        $entityManager->remove($mapping);
        $entityManager->flush();
        $this->addFlash('success', 'Mapeamento removido.');

        return $this->redirectToRoute('app_admin_column_mapping_package', ['id' => $packageId]);
    }

    private function bindFromRequest(DatasetColumnMapping $mapping, Request $request): DatasetColumnMapping
    {
        $mapping
            ->setOriginalColumn((string) $request->request->get('original_column', ''))
            ->setNormalizedColumn((string) $request->request->get('normalized_column', ''))
            ->setTargetDataType((string) $request->request->get('target_data_type', DatasetColumnMapping::TYPE_STRING))
            ->setNormalizationRule($request->request->get('normalization_rule') ?: null)
            ->setRequiredField((bool) $request->request->get('required_field', false))
            ->setIsActive((bool) $request->request->get('is_active', true))
        ;

        return $mapping;
    }

    private function validateMapping(DatasetColumnMapping $mapping): array
    {
        $errors = [];

        if ('' === trim($mapping->getOriginalColumn())) {
            $errors[] = 'O campo "Coluna original" é obrigatório.';
        }

        if ('' === trim($mapping->getNormalizedColumn())) {
            $errors[] = 'O campo "Campo normalizado" é obrigatório.';
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $mapping->getNormalizedColumn())) {
            $errors[] = 'O campo normalizado deve usar snake_case (ex: nome_fantasia).';
        }

        if (!in_array($mapping->getTargetDataType(), DatasetColumnMapping::getAvailableTypes(), true)) {
            $errors[] = 'Tipo de dado inválido.';
        }

        return $errors;
    }

    private function buildTypeLabelMap(): array
    {
        return [
            'string' => 'Texto curto (string)',
            'text' => 'Texto longo (text)',
            'integer' => 'Inteiro',
            'decimal' => 'Decimal',
            'boolean' => 'Booleano',
            'date' => 'Data',
            'datetime' => 'Data e hora',
            'json' => 'JSON',
            'geometry' => 'Geometria',
        ];
    }

    private function buildRuleLabelMap(): array
    {
        return [
            'trim' => 'Remover espaços',
            'uppercase' => 'Converter para maiúsculas',
            'lowercase' => 'Converter para minúsculas',
            'normalize_uf' => 'Normalizar UF (sigla)',
            'normalize_cnpj' => 'Normalizar CNPJ',
            'normalize_cpf' => 'Normalizar CPF',
            'normalize_phone' => 'Normalizar telefone',
            'normalize_date' => 'Normalizar data',
            'normalize_city' => 'Normalizar município',
        ];
    }
}
