<?php

namespace App\Command;

use App\Entity\DatasetResource;
use App\Service\DataPipeline\PipelineJobService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:data-pipeline:run-resource', description: 'Executa a pipeline operacional para um resource CKAN específico.')]
final class RunDatasetPipelineCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PipelineJobService $pipelineJobService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('resourceId', InputArgument::REQUIRED, 'ID do dataset_resource a processar.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $resourceId = (int) $input->getArgument('resourceId');

        /** @var DatasetResource|null $resource */
        $resource = $this->entityManager->getRepository(DatasetResource::class)->find($resourceId);
        if (!$resource instanceof DatasetResource) {
            $io->error(sprintf('Resource %d não encontrado.', $resourceId));

            return Command::FAILURE;
        }

        try {
            $result = $this->pipelineJobService->executePipeline($resource);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success($result['message']);
        $io->definitionList(
            ['Resource ID' => $resource->getResourceId()],
            ['Arquivo RAW' => $result['rawFile']->getLocalPath()],
            ['Schema detectado' => (string) $result['schemaCount']],
            ['Linhas lidas' => (string) ($result['preview']['totalRows'] ?? 0)],
            ['Colunas lidas' => (string) ($result['preview']['totalColumns'] ?? 0)],
        );

        return Command::SUCCESS;
    }
}