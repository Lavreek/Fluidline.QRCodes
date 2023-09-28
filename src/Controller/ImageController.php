<?php

namespace App\Controller;

use App\Form\PngImageType;
use Endroid\QrCode\Builder\BuilderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImageController extends AbstractController
{
    #[Route('/images', name: 'app_images')]
    public function index(Request $request, BuilderInterface $builder): Response
    {
        $pngImageForm = $this->createForm(PngImageType::class);
        $pngImageForm->handleRequest($request);

        $svgs_path = $this->getParameter('svgs');

        if (!is_dir($svgs_path)) {
            mkdir($svgs_path, recursive: true);
        }

        if ($pngImageForm->isSubmitted() and $pngImageForm->isValid()) {
            $formData = $pngImageForm->getData();

            /** @var UploadedFile $formFile */
            $formFile = $formData['file'];

            $im = new \Imagick();
            $im->readImageBlob($formFile->getContent());
            $im->setImageFormat('svg');
            $im->writeImage($svgs_path . $formFile->getClientOriginalName()  . ".svg");
            $im->clear();
            $im->destroy();
        }

        $svgsFiles = array_diff(
            scandir($svgs_path, SCANDIR_SORT_DESCENDING), ['.', '..']
        );

        return $this->render('image/index.html.twig', [
            'files' => $svgsFiles,
            'png_form' => $pngImageForm->createView(),
        ]);
    }
}
