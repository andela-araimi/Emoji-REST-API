<?php

/**
 * @author: Raimi Ademola <ademola.raimi@andela.com>
 * @copyright: 2016 Andela
 */
namespace Demo;


use Exception;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ResponseInterface as Response;

class EmojiController
{
    public $authController;

    /**
     * This is a constructor; a default method  that will be called automatically during class instantiation.
     */
    public function __construct()
    {
        $this->authController = new AuthController();
    }

    /**
     * Get all emojis.
     *
     * @param Slim\Http\Request  $request
     * @param Slim\Http\Response $response
     * @param array              $args
     *
     * @return Slim\Http\Response
     */
    public function getAllEmojis($request, $response)
    {
        $emojis = Emoji::all();
        
        if (!count($emojis) > 0) {
            return $response->withJson(['message' => 'Oops, No Emoji to display'], 404);
        }

        $emojiArray = [];

        foreach($emojis as $emoji) {
            $emojiProperty = [
                'id'         => $emoji->id,
                'name'       => $emoji->name, 
                'chars'      => $emoji->chars, 
                'category'   => $emoji->category,
                'created_by' => $emoji->created_by
            ];

            $keywords = [];

            foreach($emoji->keywords as $keyword) {
                array_push($keywords, $keyword->name);
            }
            
            $emojiProperty['keywords'] = $keywords;
            $emojiArray[] = $emojiProperty;
        }

        return $response->withJson($emojiArray);
    }

    /**
     * Get a single emoji.
     *
     * @param Slim\Http\Request  $request
     * @param Slim\Http\Response $response
     * @param array              $args
     *
     * @return Slim\Http\Response
     */
    public function getSingleEmoji($request, $response, $args)
    {
        if (!is_numeric($args['id'])) {
            return $response->withJson(['message' => 'The id supplied must be an integer.'], 401);
        }

        $emoji = Emoji::find($args['id']);

        if (count($emoji) < 1) {
            return $response->withJson(['message' => 'The requested Emoji is not found.'], 404);
        }

        $keywords = [];

        foreach ($emoji->keywords as $keyword) {
            array_push($keywords, $keyword->name);
        }

        $emoji = ['id'=> $emoji->id, 'name' => $emoji->name, 'chars' => $emoji->chars, 'category' => $emoji->category, 'created_by' => $emoji->created_by, 'keywords' => $keywords];

        return $response->withJson($emoji);
    }

    /**
     * This method creates emoji and keywords associated with it.
     *
     * @param $request
     * @param $response
     * @param $requestParams
     *
     * @return json response
     */
    public function CreateEmoji($request, $response, $requestParams)
    {
        $requestParams    = $request->getParsedBody();
        $validateUserData = $this->authController->validateUserData(['name', 'chars', 'category', 'keywords'], $requestParams);

        if (is_array($validateUserData)) {
            return $response->withJson($validateUserData, 400);
        }
        
        $validateEmptyInput = $this->checkEmptyInput($requestParams['name'], $requestParams['chars'], $requestParams['category'], $requestParams['keywords']);

        if (is_array($validateEmptyInput)) {
            return $response->withJson($validateEmptyInput, 401);
        }

        $validateEmojiDuplicate = $this->checkDuplicateEmoji($requestParams['name'], $requestParams['chars']);

        if (is_array($validateEmojiDuplicate)) {
            return $response->withJson($validateEmojiDuplicate, 400);
        }
    
        $emoji = Emoji::create([
            'name'       => strtolower($requestParams['name']),
            'chars'      => $requestParams['chars'],
            'category'   => $requestParams['category'],
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'created_by' => $this->getUsername($request, $response),
        ]);

        $this->createEmojiKeywords($emoji->id, $requestParams['keywords']);
    
        return $response->withJson(['message' => 'Emoji has been created successfully'], 201);
    }

    /**
     * This method updates an emoji by using Patch.
     *
     * @param $request
     * @param $response
     * @param $args
     *
     * @return json $response
     */
    public function updateEmojiByPatch($request, $response, $args)
    {
        $updateParams     = $request->getParsedBody();
        $validateUserData = $this->authController->validateUserData(['name'], $updateParams);
       
        if (is_array($validateUserData)) {
            return $response->withJson($validateUserData, 400);
        }

        $validateArgs = $this->validateArgs($request, $response, $args);

        if (is_array($validateArgs)) {
            return $response->withJson($validateArgs, 401);
        }

        Emoji::where('id', '=', $args['id'])
            ->update(['name' => strtolower($updateParams['name']), 'updated_at' => Carbon::now()->toDateTimeString()]);

        return $response->withJson(['message' => 'Emoji has been updated successfully'], 200);
    }

    /**
     * This method updates an emoji by using put.
     *
     * @param $request
     * @param $response
     * @param $args
     *
     * @return json $response
     */
    public function updateEmojiByPut($request, $response, $args)
    {
        $updateParams = $request->getParsedBody();
        $validateUserData = $this->authController->validateUserData(['name', 'chars', 'category'], $updateParams);
       
        if (is_array($validateUserData)) {
            return $response->withJson($validateUserData, 400);
        }

        $validateArgs = $this->validateArgs($request, $response, $args);

        if (is_array($validateArgs)) {
            return $response->withJson($validateArgs, 401);
        }

        Emoji::where('id', '=', $args['id'])
            ->update(['name' => strtolower($updateParams['name']), 'chars' => $updateParams['chars'], 'category' => $updateParams['category'], 'updated_at' => Carbon::now()->toDateTimeString()]);

        return $response->withJson(['message' => 'Emoji has been updated successfully'], 200);
    }

    /**
     * This method deletes an emoji.
     *
     * @param Slim\Http\Request  $request
     * @param Slim\Http\Response $response
     * @param array              $args
     *
     * @return json response
     */
    public function deleteEmoji($request, $response, $args)
    {
        $validateArgs = $this->validateArgs($request, $response, $args);
        if (is_array($validateArgs)) {
            return $response->withJson($validateArgs, 401);
        }

        Emoji::where('id', '=', $args['id'])->delete();

        return $response->withJson(['message' => 'Emoji successfully deleted.'], 200);
    }

    /**
     * This method authenticate and returns user id.
     *
     * @param $response
     * @param $request
     *
     * @return user id
     */
    private function getUsername($request, $response)
    {
        $jwtoken = $request->getHeader('HTTP_AUTHORIZATION');

        try {
            if (isset($jwtoken)) {
                $appSecret    = getenv('APP_SECRET');
                $jwt          = $jwtoken[0];
                $decodedToken = JWT::decode($jwt, $appSecret, ['HS512']);
                $tokenInfo    = (array) $decodedToken;
                $userInfo     = (array) $tokenInfo['data'];
            
                return $userInfo['username'];
            }
        } catch (Exception $e) {
            return $response->withJson(['status: fail, msg: Unauthorized']);
        }
    }

    /**
     * This method solves for rightful owner of a record.
     *
     * @param $response
     * @param $request
     * @param $args
     *
     * @return user id
     */
    private function getTheOwner($request, $response, $args)
    {
        return Capsule::table('emojis')
            ->where('id', '=', $args['id'])
            ->where('created_by', '=', $this->getUsername($request, $response));
    }

    /**
     * This method creates emoji keywords.
     *
     * @param $emoji_id
     * @param $keywords
     *
     * @return keyword id
     */
    public function createEmojiKeywords($emoji_id, $keywords)
    {
        if (isset($keywords)) {
            $splittedKeywords = explode(',', $keywords);
            $created_at       = Carbon::now()->toDateTimeString();
            $updated_at       = Carbon::now()->toDateTimeString();

            foreach ($splittedKeywords as $keyword) {
                $emojiKeyword = Keyword::create([
                    'emoji_id'     => $emoji_id,
                    'name'         => $keyword,
                    'created_at'   => $created_at,
                    'updated_at'   => $updated_at,
                ]);
            }

            return $emojiKeyword->name;
        }

        return true;
    }

    /**
     * This method checks for empty input from user.
     *
     * @param $inputName
     * @param $inputChars
     * @param $inputCategory
     * @param $inputKeywords
     *
     * @return bool
     */
    public function checkEmptyInput($inputName, $inputChars, $inputCategory, $inputKeywords)
    {
        if (empty($inputName) || empty($inputChars) || empty($inputCategory) || empty($inputKeywords)) {
            return ['message' => 'All fields must be provided.'];
        }

        return true;
    }

    /**
     * This method checks for duplicate emoji.
     *
     * @param $inputName
     * @param $inputChars
     *
     * @return bool
     */
    public function checkDuplicateEmoji($inputName, $inputChars)
    {
        $nameCheck = Capsule::table('emojis')->where('name', '=', strtolower($inputName))->get();
        $charsCheck = Capsule::table('emojis')->where('chars', '=', $inputChars)->get();
       
        if ($nameCheck || $charsCheck) {

            return ['message' => 'The emoji already exist in the database.'];
        }

        return true;
    }

    /**
     * This method checks for empty input from user.
     *
     * @param $request
     * @param $response
     * @param $args
     *
     * @return bool
     */
    public function validateArgs($request, $response, $args)
    {
        if (!is_numeric($args['id'])) {
            return ['message' => 'The id supplied must be an integer.'];
        }

        $emoji = Emoji::find($args['id']);
        
        if (count($emoji) < 1) {
            return ['message' => 'Action cannot be performed because the id supplied must be an integer'];
        }    

        if (is_null($this->getTheOwner($request, $response, $args)->first())) {
            return ['message' => 'Action cannot be performed because you are not the creator'];
        }

        return true;
    }
}
