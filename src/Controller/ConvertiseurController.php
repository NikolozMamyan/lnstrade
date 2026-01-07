<?php

namespace App\Controller;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class ConvertiseurController extends AbstractController
{
    #[Route('/lns-convert', name: 'app_convert', methods: ['GET', 'POST'])]
    public function convert(Request $request): Response
    {
        $error = null;

        // Même options que ton script
        $delimiter = ';';
        $enclosure = '"';
        $escapeChar = '\\';

        if ($request->isMethod('POST')) {
            $uploaded = $request->files->get('excel_file');

            if (!$uploaded) {
                $error = "Veuillez sélectionner un fichier.";
            } elseif (!$uploaded->isValid()) {
                $error = "Upload invalide.";
            } else {
                // EXACTEMENT comme ton ancien front : .xlsx uniquement
                $ext = strtolower((string) $uploaded->getClientOriginalExtension());
                if ($ext !== 'xlsx') {
                    $error = "Format non supporté. Merci d'envoyer un .xlsx uniquement.";
                } else {
                    try {
                        $excelPath = $uploaded->getPathname();

                        $spreadsheet = IOFactory::load($excelPath);
                        $sheet = $spreadsheet->getActiveSheet();
                        $rows = $sheet->toArray(null, true, true, true);

                        $csvFilename = 'export_' . date('Ymd_His') . '.csv';
                        $csvPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $csvFilename;

                        $fp = fopen($csvPath, 'wb');
                        if ($fp === false) {
                            throw new \RuntimeException('Impossible de créer le CSV.');
                        }

                        // Pas de BOM pour coller au comportement "simple" du script
                        foreach ($rows as $row) {
                            fputcsv($fp, array_values($row), $delimiter, $enclosure, $escapeChar);
                        }

                        fclose($fp);

                        $response = new BinaryFileResponse($csvPath);
                        $response->setContentDisposition(
                            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                            $csvFilename
                        );
                        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
                        $response->deleteFileAfterSend(true);

                        return $response;
                    } catch (\Throwable $e) {
                        $error = "Erreur de conversion : " . $e->getMessage();
                    }
                }
            }
        }

        return $this->render('convertiseur/index.html.twig', [
            'error' => $error,
        ]);
    }
}
