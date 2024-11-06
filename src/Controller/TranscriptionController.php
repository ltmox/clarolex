<?php
// src/Controller/TranscriptionController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class TranscriptionController extends AbstractController
{
    /**
     * @Route("/", name="upload_form")
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
        // Eliminar el límite de tiempo de ejecución
        set_time_limit(0);
        $file = $request->files->get('file');
        if ($file instanceof UploadedFile && $file->getClientOriginalExtension() === 'wav') {
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
            $transcriptionDir = $this->getParameter('kernel.project_dir') . '/public/transcripciones';
            $fileName = uniqid() . '.' . $file->getClientOriginalExtension();

            try {
                $file->move($uploadDir, $fileName);
                $filePath = $uploadDir . '/' . $fileName;

                // Especificar el archivo de salida para la transcripción en el directorio transcripciones
                $outputFilePath = $transcriptionDir . '/' . str_replace('.wav', '.txt', $fileName);

                // Llamar a Whisper para transcribir el archivo
                $command = escapeshellcmd("whisper-cpp --model /Users/mox/Projects/whisper.cpp/models/ggml-small.bin -l es -pc --output-txt $filePath");
                $output = shell_exec($command);

                // Mover el archivo de transcripción a la carpeta transcripciones
                if (file_exists($filePath . '.txt')) {
                    rename($filePath . '.txt', $outputFilePath);
                } else {
                    throw new \Exception('Error al generar el archivo de transcripción.');
                }

                // Leer el contenido del archivo de transcripción
                $transcriptionContent = file_get_contents($outputFilePath);

                return $this->render('index.html.twig', [
                    'transcription' => $transcriptionContent,
                    'download_link' => $this->generateUrl('download_file', ['filename' => basename($outputFilePath)])
                ]);
            } catch (FileException $e) {
                return new Response('Error al mover el archivo subido.');
            } catch (\Exception $e) {
                return new Response($e->getMessage());
            }
        }

        return new Response('Formato de archivo no permitido. Solo se permiten archivos WAV.');
    }

    /**
     * @Route("/download/{filename}", name="download_file", methods={"GET"})
     */
    public function download(string $filename): Response
    {
        $transcriptionDir = $this->getParameter('kernel.project_dir') . '/public/transcripciones';
        $filePath = $transcriptionDir . '/' . $filename;

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('El archivo no existe.');
        }

        return new BinaryFileResponse($filePath);
    }
}