<?php

/**
 * @author: Raimi Ademola <ademola.raimi@andela.com>
 * @copyright: 2016 Andela
 */
namespace Tests;

use Demo\App;
use Demo\User;
use Exception;
use Demo\Emoji;
use Demo\Keyword;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Slim\Http\Request;
use Slim\Http\Response;
use Demo\DatabaseSchema;
use Demo\AuthController;
use Demo\EmojiController;
use Slim\Http\Environment;
use org\bovigo\vfs\vfsStream;
use PHPUnit_Framework_TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

class EmojiEndpointsTest extends PHPUnit_Framework_TestCase
{
    protected $app;
    protected $schema;
    protected $emoji;
    protected $user;
    protected $envRootPath;

    /*
     * This function setup is used to initialize all needed classes
     */
    public function setUp()
    {       
        $this->root       = vfsStream::setup('home');
        $this->configFile = vfsStream::url('home/.env');
        
        $contents = [
            'APP_SECRET    = secretKey',
            'JWT_ALGORITHM = HS512',
            '[Database]',
            'driver=mysql',
            'host=localhost',
            'username=root',
            'password=',
            'charset=utf8',
            'collation=utf8_unicode_ci',
            'database=naijaEmoji'
        ];

        foreach($contents as $content) {

        $file = fopen($this->configFile, 'a');

            fwrite($file, $content."\n");
        };

        fclose($file);

        $this->app = (new App("vfs://home/"))->get();
        $this->capsule = new Capsule();
        $this->schema = new DatabaseSchema();
        $this->schema->createUsersTable();
        $this->schema->createEmojisTable();
        $this->schema->createKeywordsTable();
        $this->populateUser();
        $this->populateEmoji();
    }

    public function tearDown()
    {
        $this->schema->down();
    }

    /**
     * This method mock request for the response.
     *
     * @param  $path
     * @param  $options
     *
     * @return $request
     */
    public function request($method, $path, $options = [])
    {
        // Prepare a mock environment
        $env = Environment::mock(array_merge([
            'REQUEST_METHOD' => $method,
            'PATH_INFO'      => $path,
            'CONTENT_TYPE'   => 'application/json',
            'SERVER_NAME' => 'slim-test.dev',
        ], $options));

        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $this->response = $this->app->run(true);
    }

    /**
     * This method defines a get request for all emojis endpoint.
     *
     * @param  $path
     * @param  $options
     *
     * @return $request
     */
    public function get($path, $options = [])
    {
        $this->request('GET', $path, $options);
    }

    /**
     * @param  $path
     * @param  $options
     *
     * @return $request
     */
    public function postIndex($path, $options = [])
    {
        $this->request('POST', $path, $options);
    }

    /**
     * This method ascertain that emoji index page return status code 404.
     *
     * @param  void
     *
     * @return booleaan true
     */
    public function testPostIndex()
    {
        $this->postIndex('/', ['ACCEPT' => 'application/json']);
        $this->assertEquals('404', $this->response->getStatusCode());
    }

    /**
     * This method ascertain that emoji index page return status code 404.
     *
     * @param  void
     *
     * @return booleaan true
     */
    public function testGetIndex()
    {
        $this->get('/', ['ACCEPT' => 'application/json']);
        $this->assertEquals('200', $this->response->getStatusCode());
    }

    /*
     * This function test for warning suppressor
     */
    public function testPHPUnitWarningSuppressor()
    {
        $this->assertTrue(true);
    }

    /*
     * This function generate token for users
     */
    private function generateToken($username, $time = null)
    {
        $time = $time === null ? (time() - 10) : $time;
        $tokenId = base64_encode($time);
        $issuedAt = $time;
        $notBefore = $issuedAt + 10;
        $expire = $issuedAt + 200;
        $secretKey = getenv('APP_SECRET'); // or get the app key from the config file.
        $JWTToken = [
            'iat'  => $issuedAt,
            'jti'  => $tokenId,
            'nbf'  => 1455307623 + 10,
            'exp'  => 1481307683,
            'data' => ['username' => $username],
        ];

        $jwt = JWT::encode(
            $JWTToken,     //Data to be encoded in the JWT
            $secretKey,   // The signing key
            'HS512'      // Algorithm used to sign the token, see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40#section-3
        );
    
        return $jwt;
    }

    /*
     * This function poulate the users table in the database
     */
    public function populateUser()
    {
        User::create([
            'fullname'   => 'John Test',
            'username'   => 'test',
            'password'   => 'test', 
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);

        User::create([
            'fullname'   => 'paul Test',
            'username'   => 'tester',
            'password'   => 'test', 
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }

    /*
     * This function poulate the emoji table in the database
     */
    public function populateEmoji()
    {
        Emoji::create([
            'name'       => 'grin to the bone',
            'chars'      => 'u-1989',
            'category'   => 'category A',
            'keywords'   => 'sad, happy',
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'created_by' => 'test',
        ]);
    }

    /*
     * This function test for user registration
     */
    public function testCreateUser()
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/auth/register',
            'CONTENT_TYPE'   => 'application/x-www-form-urlencoded',
        ]);

        $body = [
            'fullname' => 'Gait tests',
            'username' => 'gladys',
            'password' => 'tets',
        ];

        $req = Request::createFromEnvironment($env)->withParsedBody($body);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $result = json_decode($response->getBody(), true);
        $this->assertEquals($result['message'], 'User successfully created.');
        $this->assertSame($response->getStatusCode(), 201);
    }

    /*
     * This function test for user registration with right input
     */
    public function testCreateUserWithEmptyInput()
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/auth/register',
            'CONTENT_TYPE'   => 'application/x-www-form-urlencoded',
        ]);

        $body = [
            'fullname' => 'Gait tests',
            'username' => 'gladys',
            'password' => '',
        ];

        $req = Request::createFromEnvironment($env)->withParsedBody($body);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $result = json_decode($response->getBody(), true);
        $this->assertEquals($result['message'], 'All fields must be provided.');
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test for user registration with incorrect field
     */
    public function testCreateUserWithIncorrectfield()
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/auth/register',
            'CONTENT_TYPE'   => 'application/x-www-form-urlencoded',
        ]);

        $body = [
            'fullname'   => 'Gait tests',
            'username'   => 'gladys',
            'password'   => 'tets',
            'wrongfield' => 'wrong',
        ];

        $req = Request::createFromEnvironment($env)->withParsedBody($body);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $result = json_decode($response->getBody(), true);
        $this->assertEquals($result['message'], 'Unwanted fields must be removed');
        $this->assertSame($response->getStatusCode(), 400);
    }

    /*
     * This function test for user incorrect login details
     */
    public function testInCorrectUserLogin()
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/auth/login',
            'CONTENT_TYPE'   => 'application/x-www-form-urlencoded',
            'PATH_INFO'      => '/auth',
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'username' => 'tester',
            'password' => 'xxxx',
        ]);

        $req = $req->withAttribute('issTime', 1440295673);
        $userData = $req->getParsedBody();
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $result = json_decode($response->getBody(), true);
        $data = ['message' => 'Username or Password field not valid.'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 400);
    }

    /*
     * This function test for user unwanted field
     */
    public function testuserLoginIncorrectfield()
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/auth/login',
            'CONTENT_TYPE'   => 'application/json',
            'PATH_INFO'      => '/auth',
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'username'   => 'gladys',
            'password'   => 'tets',
            'wrongfield' => 'wrong',
        ]);

        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $result = json_decode($response->getBody(), true);
        $data = ['message' => 'Unwanted fields must be removed'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 400);
    }

    /*
     * This function test for emoji creation
     */
    public function testPostEmoji()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->username, 1440295673);

         $env = Environment::mock([
            'REQUEST_METHOD'     => 'POST',
            'REQUEST_URI'        => '/emojis',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'name'       => 'grinner',
            'chars'      => 'u70m0',
            'category'   => 'Category B',
            'keywords'   => 'sad',
        ]);
        
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $result = json_decode($response->getBody(), true);
        $data = ['message' => 'Emoji has been created successfully'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 201);
    }

    /*
     * This function test for user emoji already exist in the database
     */
    public function testPostEmojiALreadyExit()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->username, 1440295673);

         $env = Environment::mock([
            'REQUEST_METHOD'     => 'POST',
            'REQUEST_URI'        => '/emojis',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'name'       => 'grin to the bone',
            'chars'      => 'u-1989',
            'category'   => 'Category B',
            'keywords'   => 'sad',
        ]);
        
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $result = json_decode($response->getBody(), true);
        $data = ['message' => 'The emoji already exist in the database.'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 400);
    }

    /*
     * This function test for incomplete field during emoji creation
     */
    public function testPostEmojiWithIncompleteField()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->username, 1440295673);

         $env = Environment::mock([
            'REQUEST_METHOD'     => 'POST',
            'REQUEST_URI'        => '/emojis',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'name'       => 'This is a new emoji',
            'chars'      => '90-poul',
            'category'   => 'Category B',
            'keywords'   => '',
        ]);

        Emoji::create([
            'name'       => 'name',
            'chars'      => 'chars',
            'category'   => 'category',
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'created_by' => $user->username,
        ]);
        
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $result = json_decode($response->getBody(), true);
        $data = ['message' => 'All fields must be provided.'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test for emoji creation with wrong field
     */
    public function testPostEmojiWithWrongField()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->username, 1440295673);

         $env = Environment::mock([
            'REQUEST_METHOD'     => 'POST',
            'REQUEST_URI'        => '/emojis',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'name'       => 'This is a new emoji',
            'chars'      => '90-poul',
            'category'   => 'Category B',
            'keywords'   => 'sad',
            'class'      => 'class A'
        ]);

        Emoji::create([
            'name'       => 'name',
            'chars'      => 'chars',
            'category'   => 'category',
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'created_by' => $user->username,
        ]);
        
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $result = json_decode($response->getBody(), true);
        $data = ['message' => 'Unwanted fields must be removed'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 400);
    }

    /*
     * This function test for retieving all emojis
     */
    public function testgetAllEmojis()
    {  
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/emojis',
            'CONTENT_TYPE'   => 'application/json',
            'PATH_INFO'      => '/emojis',
        ]);

        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $this->assertSame($response->getStatusCode(), 200);
    }

    /*
     * This function test for retieving single emoji
     */
    public function testGetSingleEmoji()
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/emojis/1',
            'CONTENT_TYPE'   => 'application/json',
            'PATH_INFO'      => '/emojis',
        ]);

        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $this->assertSame($response->getStatusCode(), 200);
    }

    /*
     * This function test for retrieving single emoji does not exist
     */
    public function testGetSingleEmojiNotExist()
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/emojis/11111',
            'CONTENT_TYPE'   => 'application/json',
            'PATH_INFO'      => '/emojis',
        ]);

        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'The requested Emoji is not found.'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 404);
    }

    /*
     * This function test for invalid argumement from user
     */
    public function testThatArgIsANum()
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/emojis/prosper',
            'CONTENT_TYPE'   => 'application/json',
            'PATH_INFO'      => '/emojis',
        ]);

        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'The id supplied must be an integer.'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test for editing emoji with put with field
     */
    public function testEditEmojiWithPutWithWrongFields()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->Id);

        $env = Environment::mock([
            'REQUEST_METHOD'     => 'PUT',
            'REQUEST_URI'        => '/emojis/1',
            'CONTENT_TYPE'       => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody(     [
            'name'       => 'KISSING FACE',
            'chars'      => '/u{1F603}',
            'category'   => 'Category A',
            'keywords'   => 'sad'
        ]);

        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'Unwanted fields must be removed'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 400);
    }

    /*
     * This function test for editing emoji with put by different creator
     */
    public function testEditEmojiWithPutByDiffCreator()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->id);

        $env = Environment::mock([
            'REQUEST_METHOD'     => 'PUT',
            'REQUEST_URI'        => '/emojis/1',
            'CONTENT_TYPE'       => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'name'       => 'KISSING FACE',
            'chars'       => '/u{1F603}',
            'category'   => 'Category A',
        ]);

        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'Action cannot be performed because you are not the creator'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test for editing emoji with put with invalid id
     */
    public function testEditEmojiWithPutWithUnExistId()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->username);

        $env = Environment::mock([
            'REQUEST_METHOD'     => 'PUT',
            'REQUEST_URI'        => '/emojis/111111',
            'CONTENT_TYPE'       => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'name'       => 'KISSING FACE',
            'chars'       => '/u{1F603}',
            'category'   => 'category D',
        ]);

        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'Action cannot be performed because the id supplied must be an integer'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test for editing emoji partially with wrong field
     */
    public function testEditEmojiPartiallyWithWrongFields()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->username);

        $env = Environment::mock([
            'REQUEST_METHOD'     => 'PATCH',
            'REQUEST_URI'        => '/emojis/1',
            'CONTENT_TYPE'       => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'name'     => 'WINKING FACE',
            'category' => 'category A',
        ]);

        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'Unwanted fields must be removed'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 400);
    }

    /*
     * This function test for editing partially by different craetor
     */
    public function testEditEmojiPartiallByDiffCreator()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->id);
        
        $env = Environment::mock([
            'REQUEST_METHOD'     => 'PUT',
            'REQUEST_URI'        => '/emojis/1',
            'CONTENT_TYPE'       => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'name'       => 'KISSING FACE',
        ]);

        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'Action cannot be performed because you are not the creator'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test for editing emoji partially with invalid id
     */
    public function testEditEmojiPartiallyWithUnExistId()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->username);

        $env = Environment::mock([
            'REQUEST_METHOD'     => 'PATCH',
            'REQUEST_URI'        => '/emojis/1222222',
            'CONTENT_TYPE'       => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'name'       => 'WINKING FACE',
        ]);

        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'Action cannot be performed because the id supplied must be an integer'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test for editing emoji partially with invalid id
     */
    public function testEditEmojiPartiallyWithStringId()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->username);

        $env = Environment::mock([
            'REQUEST_METHOD'     => 'PATCH',
            'REQUEST_URI'        => '/emojis/prosper',
            'CONTENT_TYPE'       => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'name'       => 'WINKING FACE',
        ]);

        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'The id supplied must be an integer.'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test for editing emoji with put with invalid id
     */
    public function testEditEmojiWithPutWithStringID()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->username);

        $env = Environment::mock([
            'REQUEST_METHOD'     => 'PUT',
            'REQUEST_URI'        => '/emojis/prosper',
            'CONTENT_TYPE'       => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $req = $req->withParsedBody([
            'name'       => 'KISSING FACE',
            'chars'       => '/u{1F603}',
            'category'   => 'category D',
        ]);

        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'The id supplied must be an integer.'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test for deleting emoji by different creator
     */
    public function testDeleteEmojiWithDiffCreator()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->id);

        $env = Environment::mock([
            'REQUEST_METHOD'     => 'DELETE',
            'REQUEST_URI'        => '/emojis/1',
            'CONTENT_TYPE'       => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'Action cannot be performed because you are not the creator'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test for deleting emoji with invalid id
     */
    public function testDeleteEmojiWithStringId()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->username);

        $env = Environment::mock([
            'REQUEST_METHOD'     => 'DELETE',
            'REQUEST_URI'        => '/emojis/prosper',
            'CONTENT_TYPE'       => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'The id supplied must be an integer.'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test for deleting emoji with invalid id
     */
    public function testDeleteEmojiWithUnExistId()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->username);

        $env = Environment::mock([
            'REQUEST_METHOD'     => 'DELETE',
            'REQUEST_URI'        => '/emojis/1111',
            'CONTENT_TYPE'       => 'application/x-www-form-urlencoded',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'Action cannot be performed because the id supplied must be an integer'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test user logout with correct token
     */
    public function testuserLogoutWithToken()
    {
        $env = Environment::mock([
            'REQUEST_METHOD'     => 'GET',
            'REQUEST_URI'        => '/auth/logout',
            'CONTENT_TYPE'       => 'application/json',
        ]);

        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);

        $result = ['message' => 'User unauthorized due to empty token'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 401);
    }

    /*
     * This function test user logout successfully
     */
    public function testuserLogoutSuccessfully()
    {
        $user = User::find(1);
        $token = $this->generateToken($user->username);
        $env = Environment::mock([
            'REQUEST_METHOD'     => 'GET',
            'REQUEST_URI'        => '/auth/logout',
            'CONTENT_TYPE'       => 'application/json',
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $result = ['message' => 'Logout successful'];
        $this->assertEquals($data, $result);
        $this->assertSame($response->getStatusCode(), 200);
    }
    
    /*
     * This function test user logout without correct token
     */
    public function testuserLogoutWithoutToken()
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/auth/logout',
            'CONTENT_TYPE'   => 'application/json',
            'PATH_INFO'      => '/auth',
        ]);

        $req = Request::createFromEnvironment($env);
        $this->app->getContainer()['request'] = $req;
        $response = $this->app->run(true);
        $data = json_decode($response->getBody(), true);
        $this->assertSame($response->getStatusCode(), 401);
    }
}
    