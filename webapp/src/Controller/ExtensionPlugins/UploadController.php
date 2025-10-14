<?php
namespace App\Controller\ExtensionPlugins;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends AbstractController
{
    #[Route('/jury/upload-image-endpoint', name: 'jury_upload_image', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => ['message' => 'No file uploaded']], Response::HTTP_BAD_REQUEST);
        }
        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/tinymce';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0775, true);
        }
        $filename = uniqid('tinymce_', true) . '.' . $file->guessExtension();
        $file->move($uploadsDir, $filename);
        $url = '/uploads/tinymce/' . $filename;
        return new JsonResponse(['location' => $url]);
    }
}
