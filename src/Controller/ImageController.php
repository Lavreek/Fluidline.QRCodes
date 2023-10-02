<?php

namespace App\Controller;

use App\Form\DeleteType;
use App\Form\DownloadType;
use App\Form\PngImageType;
use App\Form\QRCodeType;
use Endroid\QrCode\Builder\BuilderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImageController extends AbstractController
{
    #[Route('/png-to-svg', name: 'app_png_to_svg')]
    public function pngToSvg(Request $request): Response
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

            try {
                $im->readImageBlob($formFile->getContent());
                $im->setImageFormat('svg');
                $im->writeImage($svgs_path . $formFile->getClientOriginalName()  . ".svg");

            } catch (\ImagickException $exception) {
                return $this->render('services/error.html.twig', [
                    'exception_handler' => 'Imagick Exception',
                    'message' => $exception->getMessage(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ]);
            }

            $im->clear();
            $im->destroy();
        }

        $svgsFiles = array_diff(
            scandir($svgs_path, SCANDIR_SORT_DESCENDING), ['.', '..']
        );

        return $this->render('image/png-to-svg.html.twig', [
            'files' => $svgsFiles,
            'png_form' => $pngImageForm->createView(),
        ]);
    }

    #[Route('/serial-to-svg', name: 'app_serial_to_svg')]
    public function serialToSvg(Request $request, BuilderInterface $builder): Response
    {
        $QRCodeForm = $this->createForm(QRCodeType::class);
        $QRCodeForm->handleRequest($request);

        $deleteForm = $this->createForm(DeleteType::class);
        $downloadForm = $this->createForm(DownloadType::class);

        $pngs_path = $this->getParameter('pngs');
        $qrcode_path = $this->getParameter('qr_codes');

        foreach ([$pngs_path, $qrcode_path] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, recursive: true);
            }
        }

        if ($QRCodeForm->isSubmitted() and $QRCodeForm->isValid()) {
            $formData = $QRCodeForm->getData();

            $serials = explode(';', $formData['serial']);
            $sample = $formData['sample'];
            $size = $formData['size'];

            foreach ($serials as $serial) {
                if (empty($serial)) {
                    continue;
                }

                $data = sprintf($sample, $serial);

                $qrcode = $builder
                    ->data($data)
                    ->size($size)
                    ->margin(20)
                    ->build()
                ;

                $qrcode->saveToFile($pngs_path . $serial . ".png");
            }
        }

        $pngsFiles = array_diff(
            scandir($pngs_path, SCANDIR_SORT_DESCENDING), ['.', '..']
        );

        foreach ($pngsFiles as $file) {
            $fileinfo = pathinfo($file);

            $im = new \Imagick();
            try {
                $im->readImageBlob(file_get_contents($pngs_path . $file) ."asd");
                $im->setImageFormat('svg');
                $im->writeImage($qrcode_path . $fileinfo['filename'] . ".svg");

            } catch (\ImagickException $exception) {
                return $this->render('services/error.html.twig', [
                    'exception_handler' => 'Imagick Exception',
                    'message' => $exception->getMessage(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ]);
            }

            $im->clear();
            $im->destroy();

            unlink($pngs_path . $file);
        }

        $files = array_diff(
            scandir($qrcode_path, SCANDIR_SORT_DESCENDING), ['.', '..']
        );

        return $this->render('image/serial-to-svg.html.twig', [
            'files' => $files,
            'download_form' => $downloadForm->createView(),
            'delete_form' => $deleteForm->createView(),
            'qrcode_form' => $QRCodeForm->createView(),
        ]);
    }

    #[Route('/download', name: 'app_download')]
    public function downloadImages(Request $request): Response
    {
        $qrcode_path = $this->getParameter('qr_codes');
        $public_path = $this->getParameter('public_path');

        $downloadForm = $this->createForm(DownloadType::class);
        $downloadForm->handleRequest($request);

        $files = array_diff(
            scandir($qrcode_path, SCANDIR_SORT_DESCENDING), ['.', '..']
        );

        if ($downloadForm->isSubmitted() and $downloadForm->isValid()) {
            $zip = new \ZipArchive();
            $zip->open($public_path . '/archive.zip', \ZipArchive::CREATE|\ZipArchive::OVERWRITE);

            while ($files) {
                $file = array_shift($files);

                $zip->addFile($qrcode_path . $file, $file);
            }

            $zip->close();
        }

        $response = new Response();

        $response->headers->set('Content-type', 'application/octet-stream');

        $response->headers->set('Content-Disposition', sprintf(
            'attachment; filename="%s"', 'archive.zip'
        ));

        $archivePath = $public_path . '/archive.zip';

        if (file_exists($archivePath)) {
            $response->setContent(file_get_contents($public_path . '/archive.zip'));

        } else {
            $response->setContent('');
        }

        $response->setStatusCode(200);
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        return $response;
    }

    #[Route('/delete_images', name: 'app_delete')]
    public function deleteImages(Request $request): Response
    {
        $qrcode_path = $this->getParameter('qr_codes');

        $deleteForm = $this->createForm(DeleteType::class);
        $deleteForm->handleRequest($request);

        $files = array_diff(
            scandir($qrcode_path, SCANDIR_SORT_DESCENDING), ['.', '..']
        );

        if ($deleteForm->isSubmitted() and $deleteForm->isValid()) {
            while ($files) {
                $file = array_shift($files);

                unlink($qrcode_path . $file);
            }
        }

        return $this->redirectToRoute('app_serial_to_svg');
    }
}
