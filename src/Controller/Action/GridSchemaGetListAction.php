<?php

namespace Popov\DatagridBundle\Controller\Action;

use Popov\DatagridBundle\Discovery\GridRegistry;
use Popov\DatagridBundle\Schema\GridSchema;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GridSchemaGetListAction
{
    public function __construct(
        protected ContainerInterface $container,
        protected GridRegistry $gridRegistry,
        protected GridSchema $gridSchema
    ) {}

    /**
     * @Route("/api/v1/grids", name="api_grids_list", methods={"GET"})
     * @Route("/api/v1/grids/{gridId}", name="api_grids_get", requirements={"gridId"="\w+"}, methods={"GET"})
     */
    public function __invoke(?string $gridId = null): JsonResponse
    {
        $grids = $this->gridRegistry->all();
        if (null !== $gridId) {
            if (!isset($grids[$gridId])) {
                return new JsonResponse(['error' => 'unknown_grid'], Response::HTTP_NOT_FOUND, ['Cache-Control' => 'no-store']);
            }

            $grids = [$gridId => $grids[$gridId]];
        }

        $schemas = [];
        foreach ($grids as $gridClass) {
            $grid = $this->container->get($gridClass)->init()->getDatagrid();
            $schemas[$grid->getId()] = $this->gridSchema->create($grid);
        }

        return new JsonResponse(['data' => $schemas], Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }
}
