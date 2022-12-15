<?php

namespace App\Services\Hotel;

use App\Common\FilterException;
use App\Common\SingletonTrait;
use App\Common\Timers;
use App\Entities\Database;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;
use App\Services\Room\RoomService;
use Exception;
use PDO;
use PDOStatement;

class OneRequestHotelService extends AbstractHotelService
{
    use SingletonTrait;

    protected function __construct () {
        parent::__construct( new RoomService() );
    }

    /**
     * Récupère une nouvelle instance de connexion à la base de donnée
     *
     * @return PDO
     * @noinspection PhpUnnecessaryLocalVariableInspection
     */
    protected function getDB () : PDO {
        $timer = Timers::getInstance();
        $timerId = $timer->startTimer('getDB');
        $pdo = Database::get();
        $timer->endTimer('getDB', $timerId);
        return $pdo;
    }

    protected function buildQuery( array $args ) : PDOStatement{
        $query = "SELECT
                     USER.ID AS user_id,
                     USER.display_name AS user_name,
                     address1.meta_value AS address_1,
                     address2.meta_value AS address_2,
                     addressCity.meta_value AS address_city,
                     addressZip.meta_value AS address_zip,
                     addressCountry.meta_value AS address_country,
                     phone.meta_value AS phone,
                     lat.meta_value AS latitude,
                     lng.meta_value AS longitude,";
        if(isset($args['lat']) && isset($args['lng']) && isset($args['distance']))
            $query .= "111.111 * DEGREES(
                                 ACOS(
                                         LEAST(
                                                 1.0,
                                                 COS(RADIANS(lat.meta_value)) * COS(RADIANS(46.988708)) * COS(
                                                         RADIANS(lng.meta_value - 3.160778)
                                                  ) + SIN(RADIANS(lat.meta_value)) * SIN(RADIANS(46.988708))
                                          )
                                  )
                          ) AS distanceKM,";
        $query .= "coverImage.meta_value AS coverImage,
                     COUNT(rating.meta_value) AS countRating,
                     AVG(rating.meta_value) AS rating,
                     cheapestRoom.ID AS CRID,
                     cheapestRoom.title AS CRTitle,
                     cheapestRoom.price AS CRPrice,
                     cheapestRoom.coverImage AS CRCoverImage,
                     cheapestRoom.room AS CRRoom,
                     cheapestRoom.bathroom AS CRBathroom,
                     cheapestRoom.surface AS CRSurface,
                     cheapestRoom.TYPE AS CRType
                     
                     FROM wp_users AS USER
                     
                     INNER JOIN wp_posts AS post
                        ON USER.ID = post.post_author
                    
                    INNER JOIN wp_usermeta AS address1
                        ON USER.ID = address1.user_id AND address1.meta_key = 'address_1'
                            
                    INNER JOIN wp_usermeta AS address2
                        ON USER.ID = address2.user_id AND address2.meta_key = 'address_2'
                            
                    INNER JOIN wp_usermeta AS addressCity
                        ON USER.ID = addressCity.user_id AND addressCity.meta_key = 'address_city'
                            
                    INNER JOIN wp_usermeta AS addressZip
                        ON USER.ID = addressZip.user_id AND addressZip.meta_key = 'address_zip'
                            
                    INNER JOIN wp_usermeta AS addressCountry
                        ON USER.ID = addressCountry.user_id AND addressCountry.meta_key = 'address_country'
                    
                    INNER JOIN wp_usermeta AS phone
                        ON USER.ID = phone.user_id AND phone.meta_key = 'phone'
                            
                    INNER JOIN wp_usermeta AS lat
                        ON USER.ID = lat.user_id AND lat.meta_key = 'geo_lat'
                            
                    INNER JOIN wp_usermeta AS lng
                        ON USER.ID = lng.user_id AND lng.meta_key = 'geo_lng'
                            
                    INNER JOIN wp_usermeta AS coverImage
                        ON USER.ID = coverImage.user_id AND coverImage.meta_key = 'coverImage'
                            
                    INNER JOIN wp_postmeta AS rating
                        ON post.ID = rating.post_id AND rating.meta_key = 'rating' AND post.post_type = 'review'
                            
                    INNER JOIN (
                        SELECT
                            post.post_author AS post_author,
                            post.ID AS ID,
                            post.post_title AS title,
                            MIN(
                                CAST(price.meta_value AS DECIMAL)
                            ) AS price,
                            coverImage.meta_value AS coverImage,
                            room.meta_value AS room,
                            bathroom.meta_value AS bathroom,
                            surface.meta_value AS surface,
                            TYPE.meta_value AS TYPE
                     
                        FROM wp_posts AS post
                    
                        INNER JOIN wp_postmeta AS price
                            ON price.post_id = post.ID AND price.meta_key = 'price'
                    
                        INNER JOIN wp_postmeta AS coverImage
                            ON coverImage.post_id = post.ID AND coverImage.meta_key = 'coverImage'
                    
                        INNER JOIN wp_postmeta AS room
                            ON room.post_id = post.ID AND room.meta_key = 'bedrooms_count'
                    
                        INNER JOIN wp_postmeta AS bathroom
                            ON bathroom.post_id = post.ID AND bathroom.meta_key = 'bathrooms_count'
                    
                        INNER JOIN wp_postmeta AS surface
                            ON surface.post_id = post.ID AND surface.meta_key = 'surface'
                    
                        INNER JOIN wp_postmeta AS TYPE
                            ON TYPE.post_id = post.ID AND TYPE.meta_key = 'type'
                        
                        WHERE 
                            post.post_author = :hotelId AND post.post_type = 'room' ";

        $whereClauses = [];
        if ( isset( $args['surface']['min'] ))
            $whereClauses[] = 'surface.meta_value >= :surfaceMin';

        if ( isset( $args['surface']['max'] ))
            $whereClauses[] = 'surface.meta_value <= :surfaceMax';

        if ( isset( $args['price']['min'] ) )
            $whereClauses[] = 'price.meta_value >= :priceMin';

        if ( isset( $args['price']['max'] ))
            $whereClauses[] = 'price.meta_value <= :priceMax';

        if ( isset( $args['rooms'] ))
            $whereClauses[] = 'room.meta_value >= :room';

        if ( isset( $args['bathRooms'] ) )
            $whereClauses[] = 'bathroom.meta_value >= :bathroom';

        if ( isset( $args['types'] ) && ! empty( $args['types'] ) ) {
            $sentence = 'TYPES.meta_value IN(';
            for ($i = 0; $i < count($args['types']); $i++) {
                $sentence .= "'" . $args['types'][$i] . "'";

                if ($i + 1 < count($args['types'])) {
                    $sentence .= ",";
                }
            }
            $sentence .= ")";
            $whereClauses[] = $sentence;
        }


        if(count($whereClauses) > 0){
            $query .= ' AND ' .implode(' AND ', $whereClauses);
        }
        $query .= " GROUP BY post.post_author
                    ) AS cheapestRoom
                    ON USER.ID = cheapestRoom.post_author
            
                    GROUP BY USER.ID";

        if(isset($args['lat']) && isset($args['lng']) && isset($args['distance'])){
            $query .= "HAVING distanceKM < 30;";
        }

        return $this->getDB()->prepare($query);
    }

    /**
     * Construit une ShopEntity depuis un tableau associatif de données
     *
     * @throws Exception
     */
    protected function convertEntityFromArray ( array $args ) : HotelEntity {

    }


    /**
     * Retourne une liste de boutiques qui peuvent être filtrées en fonction des paramètres donnés à $args
     *
     * @param array{
     *   search: string | null,
     *   lat: string | null,
     *   lng: string | null,
     *   price: array{min:float | null, max: float | null},
     *   surface: array{min:int | null, max: int | null},
     *   bedrooms: int | null,
     *   bathrooms: int | null,
     *   types: string[]
     * } $args Une liste de paramètres pour filtrer les résultats
     *
     * @throws Exception
     * @return HotelEntity[] La liste des boutiques qui correspondent aux paramètres donnés à args
     */
    public function list(array $args = []): array
    {
        // TODO: Implement list() method.
    }
}