<?php

namespace App\Controller\Admin\Data;

use App\Entity\User;
use App\Repository\Data\DataQualityReportRepository;
use App\Repository\Data\RawFileRepository;
use App\Repository\DataProviderRepository;
use App\Repository\ProviderPackageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/data-pipeline/catalog')]
#[IsGranted(User::ROLE_ADMIN)]
final class DataCatalogController extends AbstractController
{
    #[Route('', name: 'app_admin_data_catalog', methods: ['GET'])]
    public function index(
        ProviderPackageRepository $packageRepository,
        DataProviderRepository $providerRepository,
        RawFileRepository $rawFileRepository,
        DataQualityReportRepository $qualityReportRepository,
    ): Response {
        $packages = $packageRepository->findBy([], ['updatedAt' => 'DESC', 'title' => 'ASC']);

        $catalogEntries = [];
        foreach ($packages as $package) {
            $latestRawFile = $this->resolveLatestRawFile($package);
            $qualityReport = null;

            if (null !== $latestRawFile) {
                $qualityReport = $qualityReportRepository->findLatestForRawFile($latestRawFile);
            }

            $catalogEntries[] = [
                'package' => $package,
                'provider' => $package->getDataProvider(),
                'rawFile' => $latestRawFile,
                'qualityReport' => $qualityReport,
                'stagingAvailable' => null !== $latestRawFile && null !== $latestRawFile->getStagingPath(),
            ];
        }

        return $this->render('admin/data_pipeline/catalog_index.html.twig', [
            'catalogEntries' => $catalogEntries,
            'summary' => [
                'totalPackages' => count($packages),
                'totalProviders' => $providerRepository->countActiveProviders(),
                'withStaging' => $rawFileRepository->countWithStaging(),
                'averageQuality' => $qualityReportRepository->getAverageQualityScore(),
            ],
        ]);
    }

    private function resolveLatestRawFile(\App\Entity\ProviderPackage $package): ?\App\Entity\Data\RawFile
    {
        foreach ($package->getResources() as $resource) {
            $rawFile = $resource->getLatestRawFile();
            if (null !== $rawFile) {
                return $rawFile;
            }
        }

        return null;
    }
}
