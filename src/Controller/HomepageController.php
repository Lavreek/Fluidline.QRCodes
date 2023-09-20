<?php

namespace App\Controller;

use App\Form\QRCodeType;
use Endroid\QrCode\Builder\BuilderInterface;
use Endroid\QrCodeBundle\Response\QrCodeResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomepageController extends AbstractController
{
    #[Route('/', name: 'app_root')]
    #[Route('/home', name: 'app_home')]
    #[Route('/homepage', name: 'app_homepage')]
    public function index(Request $request, BuilderInterface $builder): Response
    {
        $QRCodeForm = $this->createForm(QRCodeType::class);
        $QRCodeForm->handleRequest($request);

        $qrcode_path = $this->getParameter('qr_codes');

        if (!is_dir($qrcode_path)) {
            mkdir($qrcode_path, recursive: true);
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
                $qrcode->saveToFile($qrcode_path . time() .'-'. str_replace($search, '', $data) . ".png");
            }
        }

        $files = array_diff(
            scandir($qrcode_path), ['.', '..', '.gitignore']
        );

        return $this->render('homepage/index.html.twig', [
            'files' => $files,
            'qrcode_form' => $QRCodeForm->createView(),
        ]);
    }
}
