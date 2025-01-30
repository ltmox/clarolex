<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

class TranscriptionController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index(): Response
    {
        return $this->render('index.html.twig');
    }

    /**
     * @Route("/upload", name="upload_file", methods={"POST"})
     */
    public function upload(Request $request): Response
    {
        $file = $request->files->get('file');
        $model = $request->request->get('model');

        if ($file) {
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
            $transcriptionDir = $this->getParameter('kernel.project_dir') . '/transcripciones';

            // Generar un nombre de archivo único
            $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFileName = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $originalFileName);
            $uniqueFileName = $safeFileName . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $uploadsDir . '/' . $uniqueFileName;

            try {
                $file->move($uploadsDir, $uniqueFileName);
            } catch (FileException $e) {
                return new Response('Error al mover el archivo subido: ' . $e->getMessage());
            }

            // Verificar si el archivo se movió correctamente
            if (!file_exists($filePath)) {
                return new Response('Error: el archivo no se movió correctamente.');
            }

            // Extraer el audio si el archivo no es un .wav
            $audioFilePath = $filePath;
            if (!in_array($file->getClientOriginalExtension(), ['wav'])) {
                $audioFilePath = $uploadsDir . '/' . pathinfo($uniqueFileName, PATHINFO_FILENAME) . '.wav';
                $command = escapeshellcmd("ffmpeg -i $filePath -vn -acodec pcm_s16le -ar 16000 -ac 1 $audioFilePath");
                shell_exec($command);
            } else {
                // Convertir el archivo WAV a 16 kHz si no tiene la frecuencia de muestreo correcta
                $convertedFilePath = $uploadsDir . '/' . pathinfo($uniqueFileName, PATHINFO_FILENAME) . '-16k.wav';
                $command = escapeshellcmd("ffmpeg -i $filePath -vn -acodec pcm_s16le -ar 16000 -ac 1 $convertedFilePath");
                shell_exec($command);
                $audioFilePath = $convertedFilePath;
            }

            // Especificar el archivo de salida para la transcripción en el directorio transcripciones
            $outputFilePath = $transcriptionDir . '/' . pathinfo($uniqueFileName, PATHINFO_FILENAME) . '.txt';

            // Crear el directorio de transcripciones si no existe
            if (!is_dir($transcriptionDir)) {
                mkdir($transcriptionDir, 0775, true);
            }

            // Mapear el modelo seleccionado a su archivo correspondiente
            $modelMap = [
                'small' => '/Users/mox/Projects/claroLEX/whisper.cpp/models/ggml-small.bin',
                'medium' => '/Users/mox/Projects/claroLEX/whisper.cpp/models/ggml-medium.bin',
                'large' => '/Users/mox/Projects/claroLEX/whisper.cpp/models/ggml-large.bin',
            ];

            $modelFile = $modelMap[$model] ?? $modelMap['small'];

            // Ejecutar el comando con el modelo seleccionado
            $command = escapeshellcmd("whisper-cpp --model $modelFile -l es --print-colors --output-txt $audioFilePath");
            $colorOutput = shell_exec($command);

            // Limpiar códigos ANSI y caracteres especiales
            $cleanText = preg_replace('/\x1b\[[0-9;]*[mGKH]/', '', $colorOutput); // Elimina códigos ANSI
            $cleanText = preg_replace('/[\x00-\x1F\x7F]/', '', $cleanText); // Elimina caracteres de control
            $cleanText = preg_replace('/\[[\d:\.]+\]/', '', $cleanText); // Elimina marcas de tiempo [00:00.000]
            $cleanText = trim(preg_replace('/\s+/', ' ', $cleanText)); // Limpia espacios múltiples

            // Guardar texto limpio en archivo
            file_put_contents($outputFilePath, $cleanText);

            // Mover el archivo de transcripción a la carpeta transcripciones
            if (file_exists($audioFilePath . '.txt')) {
                rename($audioFilePath . '.txt', $outputFilePath);
            }

            return $this->render('result.html.twig', [
                'audioPath' => '/uploads/' . basename($audioFilePath),
                'colorOutput' => $colorOutput, // Aquí deberías agregar el resultado de la transcripción
            ]);
        }

        return new Response('No se ha subido ningún archivo.');
    }

    /**
     * @Route("/generate_pdf", name="generate_pdf", methods={"POST"})
     */
    public function generatePdf(Request $request): Response
    {
        $htmlContent = $request->request->get('htmlContent');

        // Convertir las imágenes a base64
        $logoIalab = base64_encode(file_get_contents($this->getParameter('kernel.project_dir') . '/public/images/ialab-logo.png'));
        $logoCaf = base64_encode(file_get_contents($this->getParameter('kernel.project_dir') . '/public/images/caf-logo.png'));
        $logoUbatec = base64_encode(file_get_contents($this->getParameter('kernel.project_dir') . '/public/images/ubatec.png'));

        // Configurar Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $html = $this->renderView('pdf.html.twig', [
            'transcription' => $htmlContent,
            'logoIalab' => $logoIalab,
            'logoCaf' => $logoCaf,
            'logoUbatec' => $logoUbatec,
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Enviar el PDF como respuesta
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="transcripcion.pdf"',
        ]);
    }
}