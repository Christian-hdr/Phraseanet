<?php

namespace Alchemy\Phrasea\PhraseanetService\Controller;

use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Controller\Controller;
use Alchemy\Phrasea\WorkerManager\Event\ExposeUploadEvent;
use Alchemy\Phrasea\WorkerManager\Event\WorkerEvents;
use GuzzleHttp\Client;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class PSExposeController extends Controller
{
    /**
     * Set access token on session 'password_access_token'
     * @param PhraseaApplication $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function authenticateAction(PhraseaApplication $app, Request $request)
    {
        $exposeConfiguration = $app['conf']->get(['phraseanet-service', 'expose-service', 'exposes'], []);
        $exposeConfiguration = $exposeConfiguration[$request->request->get('exposeName')];

        if ($exposeConfiguration == null) {
            return $this->app->json([
                'success' => false,
                'message' => 'Please, set configuration in admin!'
            ]);
        }

        $oauthClient = new Client(['base_uri' => $exposeConfiguration['auth_base_uri'], 'http_errors' => false]);

        try {
            $response = $oauthClient->post('/oauth/v2/token', [
                'json' => [
                    'client_id'     => $exposeConfiguration['auth_client_id'],
                    'client_secret' => $exposeConfiguration['auth_client_secret'],
                    'grant_type'    => 'password',
                    'username'      =>  $request->request->get('auth-username'),
                    'password'      =>  $request->request->get('auth-password')      ]
            ]);
        } catch(\Exception $e) {
            return $this->app->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        if ($response->getStatusCode() !== 200) {
            return $this->app->json([
                'success' => false,
                'message' => 'Status code: '. $response->getStatusCode()
            ]);
        }

        $tokenBody = $response->getBody()->getContents();

        $tokenBody = json_decode($tokenBody,true);
        $session = $this->getSession();

        $session->set('password_access_token', $tokenBody['access_token']);

        return $this->app->json([
            'success' => true
        ]);
    }

    /**
     * Get list of user or group if param "groups" defined
     *
     * @param PhraseaApplication $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     */
    public function listUsersAction(PhraseaApplication $app, Request $request)
    {
        $exposeConfiguration = $app['conf']->get(['phraseanet-service', 'expose-service', 'exposes'], []);
        $exposeConfiguration = $exposeConfiguration[$request->get('exposeName')];

        $userOrGroup = 'users';
        if ($request->get('groups')) {
            $userOrGroup = 'groups';
        }

        $exposeClient = new Client(['base_uri' => $exposeConfiguration['expose_base_uri'], 'http_errors' => false]);

        $accessToken = $this->getAndSaveToken($exposeConfiguration);

        $response = $exposeClient->get('/permissions/' . $userOrGroup, [
            'headers' => [
                'Authorization' => 'Bearer '. $accessToken
            ]
        ]);

        $list = [];
        if ($response->getStatusCode() == 200) {
            $list = json_decode($response->getBody()->getContents(),true);
        }

        return $app->json([
            'list' => $list
        ]);
    }

    /**
     * Add or update access control entry (ACE) for a publication
     *
     * @param PhraseaApplication $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function updatePublicationPermissionAction(PhraseaApplication $app, Request $request)
    {
        $exposeConfiguration = $app['conf']->get(['phraseanet-service', 'expose-service', 'exposes'], []);
        $exposeConfiguration = $exposeConfiguration[$request->get('exposeName')];
        $exposeClient = new Client(['base_uri' => $exposeConfiguration['expose_base_uri'], 'http_errors' => false]);

        $accessToken = $this->getAndSaveToken($exposeConfiguration);

        try {
            $response = $exposeClient->put('/permissions/ace', [
                'headers' => [
                    'Authorization' => 'Bearer '. $accessToken,
                    'Content-Type'  => 'application/json'
                ],
                'json' => $request->get('jsonData')
            ]);
        } catch(\Exception $e) {
            return $this->app->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        if ($response->getStatusCode() !== 200) {
            return $this->app->json([
                'success' => false,
                'message' => 'Status code: '. $response->getStatusCode()
            ]);
        }

        return $this->app->json([
            'success' => true,
            'message' => 'Permission successfully updated!'
        ]);
    }

    /**
     *  Get list of publication
     *  Use param "format=json" to retrieve a json
     *
     * @param PhraseaApplication $app
     * @param Request $request
     * @return string|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function listPublicationAction(PhraseaApplication $app, Request $request)
    {
        if ($request->get('exposeName') == null) {
            return $this->render("prod/WorkZone/ExposeList.html.twig", [
                'publications' => [],
            ]);
        }

        $exposeConfiguration = $app['conf']->get(['phraseanet-service', 'expose-service', 'exposes'], []);
        $exposeConfiguration = $exposeConfiguration[$request->get('exposeName')];

        $session = $this->getSession();

        if (!$session->has('password_access_token') && $exposeConfiguration['connection_kind'] == 'password' && $request->get('format') != 'json') {
            return $this->render("prod/WorkZone/ExposeOauthLogin.html.twig", [
                'exposeName' => $request->get('exposeName')
            ]);
        }

        $accessToken = $this->getAndSaveToken($exposeConfiguration);

        if ($exposeConfiguration == null ) {
            return $this->render("prod/WorkZone/ExposeList.html.twig", [
                'publications' => [],
            ]);
        }

        $exposeClient = new Client(['base_uri' => $exposeConfiguration['expose_base_uri'], 'http_errors' => false]);

        $response = $exposeClient->get('/publications?flatten=true&order[createdAt]=desc', [
            'headers' => [
                'Authorization' => 'Bearer '. $accessToken,
                'Content-Type'  => 'application/json'
            ]
        ]);

        $exposeFrontBasePath = \p4string::addEndSlash($exposeConfiguration['expose_front_uri']);
        $publications = [];

        if ($response->getStatusCode() == 200) {
            $body = json_decode($response->getBody()->getContents(),true);
            $publications = $body['hydra:member'];
        }

        if ($request->get('format') == 'json') {
            return $app->json([
                'publications' => $publications
            ]);
        }

        return $this->render("prod/WorkZone/ExposeList.html.twig", [
            'publications'          => $publications,
            'exposeFrontBasePath'   => $exposeFrontBasePath
        ]);
    }

    /**
     * Require params "exposeName" and "publicationId"
     * optional param "onlyAssets" equal to 1  to return only assets list
     *
     * @param PhraseaApplication $app
     * @param Request $request
     * @return string
     */
    public function getPublicationAction(PhraseaApplication $app, Request $request)
    {
        $exposeConfiguration = $app['conf']->get(['phraseanet-service', 'expose-service', 'exposes'], []);
        $exposeConfiguration = $exposeConfiguration[$request->get('exposeName')];

        $exposeClient = new Client(['base_uri' => $exposeConfiguration['expose_base_uri'], 'http_errors' => false]);

        $accessToken = $this->getAndSaveToken($exposeConfiguration);

        $publication = [];
        $resPublication = $exposeClient->get('/publications/' . $request->get('publicationId') , [
            'headers' => [
                'Authorization' => 'Bearer '. $accessToken,
                'Content-Type'  => 'application/json'
            ]
        ]);

        if ($resPublication->getStatusCode() != 200) {
            return $app->json([
                'success' => false,
                'message' => "An error occurred when getting publication: status-code " . $resPublication->getStatusCode()
            ]);
        }

        if ($resPublication->getStatusCode() == 200) {
            $publication = json_decode($resPublication->getBody()->getContents(),true);
        }

        if ($request->get('onlyAssets')) {
            return $this->render("prod/WorkZone/ExposePublicationAssets.html.twig", [
                'assets'        => $publication['assets'],
                'publicationId' => $publication['id']
            ]);
        }

        return $this->render("prod/WorkZone/ExposeEdit.html.twig", [
            'publication' => $publication,
            'exposeName'  => $request->get('exposeName')
        ]);
    }

    /**
     * Require params "exposeName" and "publicationId"
     * optionnal param "page"
     *
     * @param PhraseaApplication $app
     * @param Request $request
     * @return string|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getPublicationAssetsAction(PhraseaApplication $app, Request $request)
    {
        $page = $request->get('page')?:1;

        $exposeConfiguration = $app['conf']->get(['phraseanet-service', 'expose-service', 'exposes'], []);
        $exposeConfiguration = $exposeConfiguration[$request->get('exposeName')];

        $exposeClient = new Client(['base_uri' => $exposeConfiguration['expose_base_uri'], 'http_errors' => false]);

        $accessToken = $this->getAndSaveToken($exposeConfiguration);

        $resPublication = $exposeClient->get('/publications/' . $request->get('publicationId') . '/assets?page=' . $page , [
            'headers' => [
                'Authorization' => 'Bearer '. $accessToken,
                'Content-Type'  => 'application/json'
            ]
        ]);

        if ($resPublication->getStatusCode() != 200) {
            return $app->json([
                'success' => false,
                'message' => "An error occurred when getting publication assets: status-code " . $resPublication->getStatusCode()
            ]);
        }

        $pubAssets = [];
        $totalItems = 0;
        if ($resPublication->getStatusCode() == 200) {
            $body = json_decode($resPublication->getBody()->getContents(),true);
            $pubAssets = $body['hydra:member'];
            $totalItems = $body['hydra:totalItems'];
        }

        return $this->render("prod/WorkZone/ExposePublicationAssets.html.twig", [
            'pubAssets'     => $pubAssets,
            'publicationId' => $request->get('publicationId'),
            'totalItems'    => $totalItems,
            'page'          => $page
        ]);
    }

    /**
     * Require params "exposeName"
     *
     * @param PhraseaApplication $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function listProfileAction(PhraseaApplication $app, Request $request)
    {
        if ( $request->get('exposeName') == null) {
            return $app->json([
                'profiles' => [],
                'basePath' => []
            ]);
        }

        $exposeConfiguration = $app['conf']->get(['phraseanet-service', 'expose-service', 'exposes'], []);
        $exposeConfiguration = $exposeConfiguration[$request->get('exposeName')];

        $exposeClient = new Client(['base_uri' => $exposeConfiguration['expose_base_uri'], 'http_errors' => false]);

        $accessToken = $this->getAndSaveToken($exposeConfiguration);

        $profiles = [];
        $basePath = '';

        $resProfile = $exposeClient->get('/publication-profiles' , [
            'headers' => [
                'Authorization' => 'Bearer '. $accessToken,
                'Content-Type'  => 'application/json'
            ]
        ]);

        if ($resProfile->getStatusCode() != 200) {
            return $app->json([
                'success' => false,
                'message' => "An error occurred when getting publication: status-code " . $resProfile->getStatusCode()
            ]);
        }

        if ($resProfile->getStatusCode() == 200) {
            $body = json_decode($resProfile->getBody()->getContents(),true);
            $profiles = $body['hydra:member'];
            $basePath = $body['@id'];
        }

        return $app->json([
            'profiles' => $profiles,
            'basePath' => $basePath
        ]);
    }

    /**
     * Create a publication
     * Require params "exposeName" and "publicationData"
     *
     * @param PhraseaApplication $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function createPublicationAction(PhraseaApplication $app, Request $request)
    {
        $exposeName = $request->get('exposeName');
        if ( $exposeName == null) {
            return $app->json([
                'success' => false,
                'message' => "ExposeName required, select one!"
            ]);
        }

        $exposeConfiguration = $app['conf']->get(['phraseanet-service', 'expose-service', 'exposes'], []);
        $exposeConfiguration = $exposeConfiguration[$exposeName];

        $exposeClient = new Client(['base_uri' => $exposeConfiguration['expose_base_uri'], 'http_errors' => false]);

        try {
            $accessToken = $this->getAndSaveToken($exposeConfiguration);

            $response = $this->postPublication($exposeClient, $accessToken, json_decode($request->get('publicationData'), true));

            if ($response->getStatusCode() == 401) {
                $accessToken = $this->getAndSaveToken($exposeConfiguration);

                $response = $this->postPublication($exposeClient, $accessToken, json_decode($request->get('publicationData'), true));
            }

            if ($response->getStatusCode() !== 201) {
                return $app->json([
                    'success' => false,
                    'message' => "An error occurred when creating publication: status-code " . $response->getStatusCode()
                ]);
            }

            $publicationsResponse = json_decode($response->getBody(),true);
        } catch (\Exception $e) {
            return $app->json([
                'success' => false,
                'message' => "An error occurred when creating publication!"
            ]);
        }

        $path = empty($publicationsResponse['slug']) ? $publicationsResponse['id'] : $publicationsResponse['slug'] ;
        $url = \p4string::addEndSlash($exposeConfiguration['expose_front_uri']) . $path;

        $link = "<a style='color:blue;' target='_blank' href='" . $url . "'>" . $url . "</a>";

        return $app->json([
            'success' => true,
            'message' => "Publication successfully created!",
            'link'    => $link
        ]);
    }

    /**
     * Update a publication
     * Require params "exposeName" and "publicationId"
     *
     * @param PhraseaApplication $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function updatePublicationAction(PhraseaApplication $app, Request $request)
    {
        $exposeName = $request->get('exposeName');

        $exposeConfiguration = $app['conf']->get(['phraseanet-service', 'expose-service', 'exposes'], []);
        $exposeConfiguration = $exposeConfiguration[$exposeName];

        $exposeClient = new Client(['base_uri' => $exposeConfiguration['expose_base_uri'], 'http_errors' => false]);

        try {
            $accessToken = $this->getAndSaveToken($exposeConfiguration);

            $response = $this->putPublication($exposeClient, $request->get('publicationId'), $accessToken, json_decode($request->get('publicationData'), true));

            if ($response->getStatusCode() == 401) {
                $accessToken = $this->getAndSaveToken($exposeConfiguration);
                $response = $this->putPublication($exposeClient, $request->get('publicationId'), $accessToken, json_decode($request->get('publicationData'), true));
            }

            if ($response->getStatusCode() !== 200) {
                return $app->json([
                    'success' => false,
                    'message' => "An error occurred when updating publication: status-code " . $response->getStatusCode()
                ]);
            }
        } catch (\Exception $e) {
            return $app->json([
                'success' => false,
                'message' => "An error occurred when updating publication! ". $e->getMessage()
            ]);
        }

        return $app->json([
            'success' => true,
            'message' => "Publication successfully updated!"
        ]);
    }

    /**
     * Delete a Publication
     * require params "exposeName" and "publicationId"
     *
     * @param PhraseaApplication $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function deletePublicationAction(PhraseaApplication $app, Request $request)
    {
        $exposeName = $request->get('exposeName');

        $exposeConfiguration = $app['conf']->get(['phraseanet-service', 'expose-service', 'exposes'], []);
        $exposeConfiguration = $exposeConfiguration[$exposeName];

        $exposeClient = new Client(['base_uri' => $exposeConfiguration['expose_base_uri'], 'http_errors' => false]);

        try {
            $accessToken = $this->getAndSaveToken($exposeConfiguration);

            $response = $this->removePublication($exposeClient, $request->get('publicationId'), $accessToken);

            if ($response->getStatusCode() == 401) {
                $accessToken = $this->getAndSaveToken($exposeConfiguration);
                $response = $this->removePublication($exposeClient, $request->get('publicationId'), $accessToken);
            }

            if ($response->getStatusCode() !== 204) {
                return $app->json([
                    'success' => false,
                    'message' => "An error occurred when deleting publication: status-code " . $response->getStatusCode()
                ]);
            }
        } catch (\Exception $e) {
            return $app->json([
                'success' => false,
                'message' => "An error occurred when deleting publication!"
            ]);
        }

        return $app->json([
            'success' => true,
            'message' => "Publication successfully deleted!"
        ]);
    }

    /**
     * Delete asset from publication
     * require params "exposeName" ,"publicationId" and "assetId"
     *
     * @param PhraseaApplication $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function deletePublicationAssetAction(PhraseaApplication $app, Request $request)
    {
        $exposeName = $request->get('exposeName');

        $exposeConfiguration = $app['conf']->get(['phraseanet-service', 'expose-service', 'exposes'], []);
        $exposeConfiguration = $exposeConfiguration[$exposeName];

        $exposeClient = new Client(['base_uri' => $exposeConfiguration['expose_base_uri'], 'http_errors' => false]);

        try {
            $accessToken = $this->getAndSaveToken($exposeConfiguration);

            $response = $this->removeAssetPublication($exposeClient, $request->get('publicationId'), $request->get('assetId'), $accessToken);

            if ($response->getStatusCode() == 401) {
                $accessToken = $this->getAndSaveToken($exposeConfiguration);
                $response = $this->removeAssetPublication($exposeClient, $request->get('publicationId'), $request->get('assetId'), $accessToken);
            }

            if ($response->getStatusCode() !== 204) {
                return $app->json([
                    'success' => false,
                    'message' => "An error occurred when deleting asset: status-code " . $response->getStatusCode()
                ]);
            }
        } catch (\Exception $e) {
            return $app->json([
                'success' => false,
                'message' => "An error occurred when deleting asset!"
            ]);
        }

        return $app->json([
            'success' => true,
            'message' => "Asset successfully removed from publication!"
        ]);

    }

    /**
     * Add assets in a publication
     * Require params "lst" , "exposeName" and "publicationId"
     * "lst" is a list of record as "baseId_recordId"
     *
     * @param PhraseaApplication $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function addPublicationAssetsAction(PhraseaApplication $app, Request $request)
    {
        $exposeName = $request->get('exposeName');
        $publicationId = $request->get('publicationId');
        $lst =  $request->get('lst');

        if ($publicationId == null) {
            return $app->json([
                'success' => false,
                'message'   => 'Need to give publicationId to add asset in publication!'
            ]);
        }

        $exposeConfiguration = $app['conf']->get(['phraseanet-service', 'expose-service', 'exposes'], []);
        $exposeConfiguration = $exposeConfiguration[$exposeName];
        $accessToken = $this->getAndSaveToken($exposeConfiguration);

        $this->getEventDispatcher()->dispatch(WorkerEvents::EXPOSE_UPLOAD_ASSETS, new ExposeUploadEvent($lst, $exposeName, $publicationId, $accessToken));

        return $app->json([
            'success' => true,
            'message' => " Record (s) to be added to the publication!"
        ]);
    }

    /**
     * Get Token and save in session
     * @param $config
     *
     * @return mixed
     */
    private function getAndSaveToken($config)
    {
        $session = $this->getSession();

        $accessToken = '';
        if ($config['connection_kind'] == 'password') {
            $accessToken = $session->get('password_access_token');
        } elseif ($config['connection_kind'] == 'client_credentials') {
            if ($session->has('credential_access_token')) {
                $accessToken = $session->get('credential_access_token');
            } else {
                $oauthClient = new Client();

                try {
                    $response = $oauthClient->post($config['expose_base_uri'] . '/oauth/v2/token', [
                        'json' => [
                            'client_id'     => $config['expose_client_id'],
                            'client_secret' => $config['expose_client_secret'],
                            'grant_type'    => 'client_credentials',
                            'scope'         => 'publish'
                        ]
                    ]);
                } catch(\Exception $e) {
                    return null;
                }

                if ($response->getStatusCode() !== 200) {
                    return null;
                }

                $tokenBody = $response->getBody()->getContents();

                $tokenBody = json_decode($tokenBody,true);

                $session->set('credential_access_token', $tokenBody['access_token']);

                $accessToken = $tokenBody['access_token'];
            }
        }

        return $accessToken;
    }

    private function postPublication(Client $exposeClient, $token, $publicationData)
    {
        return $exposeClient->post('/publications', [
            'headers' => [
                'Authorization' => 'Bearer '. $token,
                'Content-Type'  => 'application/json'
            ],
            'json' => $publicationData
        ]);
    }

    private function putPublication(Client $exposeClient, $publicationId, $token, $publicationData)
    {
        return $exposeClient->put('/publications/' . $publicationId, [
            'headers' => [
                'Authorization' => 'Bearer '. $token,
                'Content-Type'  => 'application/json'
            ],
            'json' => $publicationData
        ]);
    }

    private function removePublication(Client $exposeClient, $publicationId, $token)
    {
        return $exposeClient->delete('/publications/' . $publicationId, [
            'headers' => [
                'Authorization' => 'Bearer '. $token
            ]
        ]);
    }

    private function removeAssetPublication(Client $exposeClient, $publicationId, $assetId, $token)
    {
        $exposeClient->delete('/publication-assets/'.$publicationId.'/'.$assetId, [
            'headers' => [
                'Authorization' => 'Bearer '. $token
            ]
        ]);

        return $exposeClient->delete('/assets/'. $assetId, [
            'headers' => [
                'Authorization' => 'Bearer '. $token
            ]
        ]);
    }


    /**
     * @return EventDispatcherInterface
     */
    private function getEventDispatcher()
    {
        return $this->app['dispatcher'];
    }

    /**
     * @return Session
     */
    private function getSession()
    {
        return $this->app['session'];
    }
}
