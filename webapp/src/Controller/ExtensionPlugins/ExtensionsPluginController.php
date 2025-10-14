<?php declare(strict_types=1);

namespace App\Controller\ExtensionPlugins;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/extensions_plugin')]
class ExtensionsPluginController extends BaseController
{
    #[Route(path: '', name: 'extensions_plugin')]
    public function index(): Response
    {
        return $this->render('extensions_plugin/index.html.twig');
    }
}
