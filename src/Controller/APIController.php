<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Entity\Song;
use App\Entity\User;
use App\Entity\Ad;

class APIController extends AbstractController
{
    public $currentVersion = 1;

    /**
     * @Route("/api/ping", name="api.ping")
     */
    public function ping()
    {
        return new JsonResponse(['version' => $this->currentVersion, 'status' => 200, 'pong' => true]);
    }

    /**
     * @Route("/api/songs/new/{offset}", name="api.songs.new")
     */
    public function songsNew(Request $request, int $offset = 0)
    {
        $em = $this->getDoctrine()->getManager();
        $data = [];

        $results = $em->getRepository(Song::class)->findBy(array(), array('id' => 'DESC'), 6, 6 * $offset);
        $baseUrl = $request->getScheme() . '://' . $request->getHttpHost() . $request->getBasePath();

        foreach($results as $result) {
            $oneResult = [];

            $oneResult['id'] = $result->getId();
            $oneResult['title'] = $result->getTitle();
            $oneResult['subtitle'] = $result->getSubtitle();
            $oneResult['artist'] = $result->getArtist();
            $oneResult['charter'] = $result->getCharter();
            $oneResult['cover'] = $baseUrl."/uploads/cover/".$result->getFileReference().".png";

            $data[] = $oneResult;
        }

        return new JsonResponse(['version' => $this->currentVersion, 'status' => 200, 'data' => $data]);
    }

    /**
     * @Route("/api/songs/popular/{offset}", name="api.songs.popular")
     */
    public function songsPopular(Request $request, int $offset = 0)
    {
        $em = $this->getDoctrine()->getManager();
        $data = [];

        $results = $em->getRepository(Song::class)->findBy(array(), array('views' => 'DESC'), 6, 6 * $offset);
        $baseUrl = $request->getScheme() . '://' . $request->getHttpHost() . $request->getBasePath();

        foreach($results as $result) {
            $oneResult = [];

            $oneResult['id'] = $result->getId();
            $oneResult['title'] = $result->getTitle();
            $oneResult['subtitle'] = $result->getSubtitle();
            $oneResult['artist'] = $result->getArtist();
            $oneResult['charter'] = $result->getCharter();
            $oneResult['cover'] = $baseUrl."/uploads/cover/".$result->getFileReference().".png";

            $data[] = $oneResult;
        }

        return new JsonResponse(['version' => $this->currentVersion, 'status' => 200, 'data' => $data]);
    }

    /**
     * @Route("/api/song/{idOrReference}", name="api.songs.detail")
     */
    public function songDetail(Request $request, $idOrReference)
    {
        $em = $this->getDoctrine()->getManager();
        $data = [];

        if(is_numeric($idOrReference)) {
            // $idOrReference is the ID
            $result = $em->getRepository(Song::class)->findOneBy(array('id' => $idOrReference));
        } else {
            // $idOrReference is the file Reference
            $result = $em->getRepository(Song::class)->findOneBy(array('fileReference' => $idOrReference));
        }
        $baseUrl = $request->getScheme() . '://' . $request->getHttpHost() . $request->getBasePath();

        if(!$result) {
            return new JsonResponse(['version' => $this->currentVersion, 'status' => 404, 'data' => []]);
        } else {
            $result->setViews($result->getViews() + 1);
            $em->persist($result);
            $em->flush();

            $data['id'] = $result->getId();
            $data['title'] = $result->getTitle();
            $data['subtitle'] = $result->getSubtitle();
            $data['artist'] = $result->getArtist();
            $data['charter'] = $result->getCharter();
            $data['uploader'] = $result->getUploader();
            $data['tags'] = explode(",", $result->getTags());
            $data['paths']['ogg'] = $baseUrl."/uploads/audio/".$result->getFileReference().".ogg";
            $data['paths']['cover'] = $baseUrl."/uploads/cover/".$result->getFileReference().".png";
            $data['paths']['zip'] = $this->generateUrl('api.songs.download', array('id' => $result->getId()), UrlGeneratorInterface::ABSOLUTE_URL);
    
            return new JsonResponse(['version' => $this->currentVersion, 'status' => 200, 'data' => $data]);
        }
    }

    /**
     * @Route("/api/song/{id}/download", name="api.songs.download")
     */
    public function songDownload(int $id)
    {
        $em = $this->getDoctrine()->getManager();

        $result = $em->getRepository(Song::class)->findOneBy(array('id' => $id));

        if(!$result) {
            return new JsonResponse(['version' => $this->currentVersion, 'status' => 404, 'data' => []]);
        } else {
            $result->setDownloads($result->getDownloads() + 1);
            $em->persist($result);
            $em->flush();

            $zipLocation = $this->getParameter('temp_path').DIRECTORY_SEPARATOR;
            $zipName = $result->getFileReference().".zip";

            $zip = new \ZipArchive;
            $zip->open($zipLocation.$zipName, \ZipArchive::CREATE);
            $zip->addFile($this->getParameter('srtb_path').DIRECTORY_SEPARATOR.$result->getFileReference().".srtb", $result->getFileReference().".srtb");
            $zip->addFile($this->getParameter('cover_path').DIRECTORY_SEPARATOR.$result->getFileReference().".png", "AlbumArt".DIRECTORY_SEPARATOR.$result->getFileReference().".png");
            $zip->addFile($this->getParameter('audio_path').DIRECTORY_SEPARATOR.$result->getFileReference().".ogg", "AudioClips".DIRECTORY_SEPARATOR.$result->getFileReference().".ogg");
            $zip->close();

            $response = new Response(file_get_contents($zipLocation.$zipName));
            $response->headers->set('Content-Type', 'application/zip');
            $response->headers->set('Content-Disposition', 'attachment;filename="' . $zipName . '"');
            $response->headers->set('Content-length', filesize($zipLocation.$zipName));
        
            @unlink($zipLocation.$zipName);
        
            return $response;
        }
    }

    /**
     * @Route("/api/ads", name="api.ads")
     */
    public function ads(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $results = $em->getRepository(Ad::class)->findBy(array('isVisible' => true), array('id' => 'DESC'), 2);
        $baseUrl = $request->getScheme() . '://' . $request->getHttpHost() . $request->getBasePath();

        if(!$results) {
            return new JsonResponse(['version' => $this->currentVersion, 'status' => 404, 'data' => []]);
        } else {
            foreach($results as $result) {
                $oneResult = [];

                $oneResult['id'] = $result->getId();
                $oneResult['title'] = $result->getTitle();
                $oneResult['type'] = $result->getType();
                $oneResult['textColor'] = $result->getTextColor();
                $oneResult['color'] = $result->getColor();
                $oneResult['button']['type'] = $result->getButtonType();
                $oneResult['button']['data'] = $result->getButtonData();
                $oneResult['isVisible'] = $result->getIsVisible();
                $oneResult['image_path'] = $baseUrl."/uploads/ads/".$result->getImagePath();

                $data[] = $oneResult;
            }
    
            return new JsonResponse(['version' => $this->currentVersion, 'status' => 200, 'data' => $data]);
        }
    }

    /**
     * @Route("/api/user/{userId}", name="api.users.detail")
     */
    public function userDetail(Request $request, int $userId)
    {
        $em = $this->getDoctrine()->getManager();
        $data = [];

        $result = $em->getRepository(User::class)->findOneBy(array('id' => $userId));
        $baseUrl = $request->getScheme() . '://' . $request->getHttpHost() . $request->getBasePath();

        if(!$result) {
            return new JsonResponse(['version' => $this->currentVersion, 'status' => 404, 'data' => []]);
        } else {
            $data['id'] = $result->getId();
            $data['username'] = $result->getUsername();
            $data['avatar'] = $baseUrl."/uploads/avatar/".$result->getUsername().".png";

            // Get User Songs
            $resultsSongs = $em->getRepository(Song::class)->findBy(array('uploader' => $result->getId()));
                 
            foreach($resultsSongs as $result) {
                $oneResult = [];

                $oneResult['id'] = $result->getId();
                $oneResult['title'] = $result->getTitle();
                $oneResult['subtitle'] = $result->getSubtitle();
                $oneResult['artist'] = $result->getArtist();
                $oneResult['charter'] = $result->getCharter();
                $oneResult['uploader'] = $result->getUploader();
                $oneResult['cover'] = $baseUrl."/uploads/cover/".$result->getFileReference().".png";

                $data['songs'][] = $oneResult;
            }
    
            return new JsonResponse(['version' => $this->currentVersion, 'status' => 200, 'data' => $data]);
        }
    }

    /**
     * @Route("/api/search/{searchQuery}", name="api.search")
     */
    public function search(Request $request, string $searchQuery)
    {
        $em = $this->getDoctrine()->getManager();
        $baseUrl = $request->getScheme() . '://' . $request->getHttpHost() . $request->getBasePath();

        $data = [];
        $data['users'] = [];
        $data['songs'] = [];

        // Users
        $resultsUsers = $em->getRepository(User::class)->createQueryBuilder('o')
                                                        ->where('o.username LIKE :query')
                                                        ->setParameter('query', '%'.$searchQuery.'%')
                                                        ->getQuery()
                                                        ->getResult();

        foreach($resultsUsers as $result) {
            $oneResult = [];

            $oneResult['id'] = $result->getId();
            $oneResult['username'] = $result->getUsername();
            $oneResult['avatar'] = $baseUrl."/uploads/avatar/".$result->getUsername().".png";

            $data['users'][] = $oneResult;
        }

        // Songs
        $resultsSongs = $em->getRepository(Song::class)->createQueryBuilder('o')
                                                        ->where('o.title LIKE :query')
                                                        ->orWhere('o.subtitle LIKE :query')
                                                        ->orWhere('o.tags LIKE :query')
                                                        ->orWhere('o.artist LIKE :query')
                                                        ->orWhere('o.charter LIKE :query')
                                                        ->setParameter('query', '%'.$searchQuery.'%')
                                                        ->getQuery()
                                                        ->getResult();
                 
        foreach($resultsSongs as $result) {
            $oneResult = [];

            $oneResult['id'] = $result->getId();
            $oneResult['title'] = $result->getTitle();
            $oneResult['subtitle'] = $result->getSubtitle();
            $oneResult['artist'] = $result->getArtist();
            $oneResult['charter'] = $result->getCharter();
            $oneResult['cover'] = $baseUrl."/uploads/cover/".$result->getFileReference().".png";

            $data['songs'][] = $oneResult;
        }

        return new JsonResponse(['version' => $this->currentVersion, 'status' => 200, 'data' => $data]);
    }
}
