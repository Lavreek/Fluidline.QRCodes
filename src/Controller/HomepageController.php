<?php

namespace App\Controller;

use App\Form\DeleteType;
use App\Form\DownloadType;
use App\Form\QRCodeType;
use Endroid\QrCode\Builder\BuilderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use ZipArchive;

class HomepageController extends AbstractController
{
    #[Route('/', name: 'app_root')]
    #[Route('/home', name: 'app_home')]
    #[Route('/homepage', name: 'app_homepage')]
    public function index(Request $request, BuilderInterface $builder): Response
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

                $search = ['/', '\\', ':', '*', '?', '"', '<', '>', '|', 'http', 'https'];
                $qrcode->saveToFile($pngs_path . time() .'-'. str_replace($search, '', $data) . ".png");
            }
        }

        $pngsFiles = array_diff(
            scandir($pngs_path, SCANDIR_SORT_DESCENDING), ['.', '..']
        );

        foreach ($pngsFiles as $file) {
            $fileinfo = pathinfo($file);

            $im = new \Imagick();
            $im->readImageBlob(file_get_contents($pngs_path . $file));
            $im->setImageFormat('svg');
            $im->writeImage($qrcode_path . $fileinfo['filename'] . ".svg");
            $im->clear();
            $im->destroy();

            unlink($pngs_path . $file);
        }

        $files = array_diff(
            scandir($qrcode_path, SCANDIR_SORT_DESCENDING), ['.', '..']
        );

        return $this->render('homepage/index.html.twig', [
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

        $response->setContent(file_get_contents($public_path . '/archive.zip'));
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

        return $this->redirectToRoute('app_home');
    }
}