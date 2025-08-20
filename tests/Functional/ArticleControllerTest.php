<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\Article;
use App\Repository\UserRepository;
use App\Repository\ArticleRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;

class ArticleControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private AbstractDatabaseTool $databaseTool;

    public function setUp(): void
    {
        //Création du client léger
        $this->client = self::createClient(server:
        [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json'
        ]);

        $this->databaseTool = self::getContainer()->get(DatabaseToolCollection::class)->get();
    }

    private function getUser(string $username = 'admin'): ?User
    {
        //On load les fixtures
        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/UserFixtures.yaml'
        ]);

        //On récupère l'utilisateur par son nom d'utilisateur
        $user = self::getContainer()->get(UserRepository::class)
            ->findOneBy(['username' => $username]);

        // On le renvois
        return $user;
    }

    public function testIndexEndPointwithNoConnectedUser(): void
    {
        $this->client->request('GET', '/api/admin/articles');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        //$this->assertResponseStatusCodeSame(401);
    }

    public function testIndexEndPointwithConnectedUser(): void
    {
        $this->client->loginUser(
            $this->getUser('user'),
            'login'
        );

        $this->client->request('GET', '/api/admin/articles');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIndexEndPointwithConnectedAdmin(): void
    {
        $this->client->loginUser(
            $this->getUser('admin'),
            'login'
        );

        $this->client->request('GET', '/api/admin/articles');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testIndexEndPointValidateStructureJsonResponse(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        $this->client->request('GET', '/api/admin/articles');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('items', $response);
        $this->assertArrayHasKey('meta', $response);
        $this->assertArrayHasKey('pages', $response['meta']);
        $this->assertArrayHasKey('total', $response['meta']);
    }

    public function testIndexEndPointValidateNumberofItemsDefault(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        //On charge les fixtures pour le test
        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixtures.yaml'
        ]);

        $this->client->request('GET', '/api/admin/articles');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(6, $response['items']);
    }

    public function testIndexEndPointValidateNumberOfItemsWithLimitParameter(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        //On charge les fixtures pour le test
        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixtures.yaml'
        ]);

        $this->client->request('GET', '/api/admin/articles?limit=1');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(1, $response['items']);
        $this->assertEquals(12, $response['meta']['pages']);
    }

    public function testIndexEndPointValidateErrorWhenLimitIsNotPositive(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        $this->client->request('GET', '/api/admin/articles?limit=-1', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        //Pas besoin de charger les fixtures ici

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('limit: This value should be positive.', $response['detail']);
    }

    public function testIndexEndPointValidateFirstItemWhenPageChange(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        //On charge les fixtures pour le test
        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixtures.yaml'
        ]);

        $this->client->request('GET', '/api/admin/articles?page=2');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('Article 7', $response['items'][0]['title']);
    }

    public function testIndexEndPointValidateErrorWhenPageIsNotPositive(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        $this->client->request('GET', '/api/admin/articles?page=-1', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        //Pas besoin de charger les fixtures ici

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('page: This value should be positive.', $response['detail']);
    }

    public function testCreateEndPointWithConnectedUser(): void
    {
        $this->client->loginUser(
            $this->getUser('user'),
            'login'
        );

        $this->client->request('POST', 'api/admin/articles');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateEndPointWithConnectedAdmin(): void
    {
        $user = $this->getUser();

        $this->client->loginUser(
            $user,
            'login'
        );

        /* $user = self::getContainer()->get(UserRepository::class)
                 ->findOneBy(['username' => 'admin']);*/

        $this->client->request('POST', '/api/admin/articles', [
            'title' => 'Article test',
            'content' => 'Article test',
            'shortContent' => 'Article test',
            'user' => $user->getId(),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testCreateEndPointValidateCreationInBdd(): void
    {
        $user = $this->getUser('admin');

        $this->client->loginUser(
            $user,
            'login'
        );

        $this->client->request('POST', '/api/admin/articles', [
            'title' => 'Article test',
            'content' => 'Article test',
            'shortContent' => 'Article test',
            'user' => $user->getId(),
        ]);

        $article = self::getContainer()->get(ArticleRepository::class)
            ->findOneBy(['title' => 'Article test']);

        $this->assertInstanceOf(Article::class, $article);
    }

  
    public function testUpdateEndpointWithConnectedUser(): void
    {
        // On connecte d'abord l'utilisateur
        $this->client->loginUser(
            $this->getUser('user'),
            'login'
        );

        $this->client->request('PATCH', '/api/admin/articles/1');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUpdateEndpointWithConnectedAdmin(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixtures.yaml'
        ]);

        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['title' => 'Article 1']);

        $this->client->request('PATCH', "/api/admin/articles/{$article->getId()}", [
            'title' => 'Article modifié',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
}
