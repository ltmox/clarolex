<?php
// filepath: /Users/mox/Projects/claroLEX/src/Controller/ReportController.php
namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends AbstractController
{
    public function generatePdf(Request $request): Response
    {
        // Obtener el texto de la transcripción desde la solicitud
        $transcription = $request->request->get('transcription');

        // Convertir las imágenes a base64
        $logoIalab = base64_encode(file_get_contents($this->getParameter('kernel.project_dir') . '/public/images/ialab-lila.png'));
        $logoCaf = base64_encode(file_get_contents($this->getParameter('kernel.project_dir') . '/public/images/caf-logo.png'));
        $logoUbatec = base64_encode(file_get_contents($this->getParameter('kernel.project_dir') . '/public/images/ubatec.png'));

        // Configurar Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');

        // Instanciar Dompdf con nuestras opciones
        $dompdf = new Dompdf($pdfOptions);

        // Obtener el contenido HTML
        $html = $this->renderView('pdf.html.twig', [
            'transcription' => $transcription,
            'logoIalab' => $logoIalab,
            'logoCaf' => $logoCaf,
            'logoUbatec' => $logoUbatec,
        ]);

        // Cargar el HTML en Dompdf
        $dompdf->loadHtml($html);

        // (Opcional) Configurar el tamaño y la orientación del papel
        $dompdf->setPaper('A4', 'portrait');

        // Renderizar el HTML como PDF
        $dompdf->render();

        // Enviar el PDF generado al navegador
        $output = $dompdf->output();
        $response = new Response($output);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="transcripcion.pdf"');

        return $response;
    }
}