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

/**
 * Une classe utilitaire pour récupérer les données des magasins stockés en base de données
 */
class UnoptimizedHotelService extends AbstractHotelService {
  
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
  
  
  /**
   * Récupère une méta-donnée de l'instance donnée
   *
   * @param int    $userId
   * @param string $key
   *
   * @return string|null
   */
  protected function getMeta ( int $userId, string $key ) : ?string {

    $db = $this->getDB();
    // SELECT * FROM wp_usermeta
    $stmt = $db->prepare( "SELECT * FROM wp_usermeta WHERE user_id = :userId AND meta_key = :key" );
    $stmt->execute(['userId'=>$userId, 'key'=>$key]);

    $result = $stmt->fetchAll( PDO::FETCH_ASSOC );

    return $result[0]['meta_value'];
  }
  
  
  /**
   * Récupère toutes les meta données de l'instance donnée
   *
   * @param HotelEntity $hotel
   *
   * @return array
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */

  protected function getMetas ( HotelEntity $hotel ) : array {
      $timer = Timers::getInstance();
      $timerId = $timer->startTimer('getMetas');
      $stmt = $this->getDB()->prepare("SELECT meta_key, meta_value FROM wp_usermeta WHERE wp_usermeta.user_id = :userId GROUP BY meta_key;");
      $stmt -> execute(['userId' => $hotel->getId()]);
      $hotelMeta = $stmt ->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_UNIQUE);

    $metaDatas = [
      'address' => [
        'address_1' => $hotelMeta['address_1'],
        'address_2' => $hotelMeta['address_2'],
        'address_city' => $hotelMeta['address_city'],
        'address_zip' => $hotelMeta['address_zip'],
        'address_country' => $hotelMeta['address_country'],
      ],
      'geo_lat' =>  $hotelMeta['geo_lat'],
      'geo_lng' =>  $hotelMeta['geo_lng'],
      'coverImage' =>  $hotelMeta['coverImage'],
      'phone' =>  $hotelMeta['phone'],
    ];
      $timer->endTimer('getMetas', $timerId);
    return $metaDatas;
  }
  
  
  /**
   * Récupère les données liées aux évaluations des hotels (nombre d'avis et moyenne des avis)
   *
   * @param HotelEntity $hotel
   *
   * @return array{rating: int, count: int}
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getReviews ( HotelEntity $hotel ) : array {
    // Récupère tous les avis d'un hotel
    $stmt = $this->getDB()->prepare( "SELECT meta_value FROM wp_posts JOIN wp_postmeta ON wp_posts.ID = wp_postmeta.post_id WHERE wp_posts.post_author = :hotelId AND meta_key = 'rating' AND post_type = 'review'" );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );
    $reviews = $stmt->fetchAll( PDO::FETCH_ASSOC );

    $reviews = array_column($reviews,"meta_value");
    $reviews = array_map('intval', $reviews);
    
    $output = [
      'rating' => round( array_sum( $reviews ) / count( $reviews ) ),
      'count' => count( $reviews ),
    ];
    
    return $output;
  }
  
  
  /**
   * Récupère les données liées à la chambre la moins chère des hotels
   *
   * @param HotelEntity $hotel
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   rooms: int | null,
   *   bathRooms: int | null,
   *   types: string[]
   * }                  $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws FilterException
   * @return RoomEntity
   */
    protected function getCheapestRoom ( HotelEntity $hotel, array $args = [] ) : RoomEntity {
        $timer = Timers::getInstance();
        $timerId = $timer->startTimer('getCheapestRoom');

        //SELECT * FROM wp_posts WHERE post_author = :hotelId AND post_type = 'room'
        $query = "SELECT
                    post.post_author AS hotelId,
                    post.ID AS ID,
                    post.post_title AS title,
                    MIN(
                        CAST(price.meta_value AS UNSIGNED)
                    ) AS prices,
                    coverImage.meta_value AS coverImages,
                    room.meta_value AS rooms,
                    bathroom.meta_value AS bathrooms,
                    surface.meta_value AS surfaces,
                    TYPES.meta_value AS TYPES,
                    latData.meta_value AS lat,
                    lngData.meta_value AS lng";

        if(isset($_GET['lat']) && isset($_GET['lng']) && isset($_GET['distance'])){
            $query .= ", 111.111 * DEGREES(
                        ACOS(
                            LEAST(
                                1.0,
                                COS(RADIANS(latData.meta_value)) * COS(RADIANS(:latitude)) * COS(
                                    RADIANS(lngData.meta_value - :longitude)
                                ) + SIN(RADIANS(latData.meta_value)) * SIN(RADIANS(:latitude))
                            )
                        )
                    ) AS distanceKM";
        }
        $query .= " FROM
                    wp_posts AS post
                INNER JOIN wp_postmeta AS price
                ON
                    price.post_id = post.ID AND price.meta_key = 'price'
                INNER JOIN wp_postmeta AS coverImage
                ON
                    coverImage.post_id = post.ID AND coverImage.meta_key = 'coverImage'
                INNER JOIN wp_postmeta AS room
                ON
                    room.post_id = post.ID AND room.meta_key = 'bedrooms_count'
                INNER JOIN wp_postmeta AS bathroom
                ON
                    bathroom.post_id = post.ID AND bathroom.meta_key = 'bathrooms_count'
                INNER JOIN wp_postmeta AS surface
                ON
                    surface.post_id = post.ID AND surface.meta_key = 'surface'
                INNER JOIN wp_postmeta AS TYPES
                ON TYPES
                    .post_id = post.ID AND TYPES.meta_key = 'type'
                INNER JOIN tp.wp_users AS USER
                ON
                    USER.ID = post.post_author
                INNER JOIN tp.wp_usermeta AS latData
                ON
                    latData.user_id = USER.ID AND latData.meta_key = 'geo_lat'
                INNER JOIN tp.wp_usermeta AS lngData
                ON
                    lngData.user_id = USER.ID AND lngData.meta_key = 'geo_lng'
                WHERE
                    post.post_author = :hotelId AND post.post_type = 'room'";

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
        $query .= " GROUP BY post.post_author";
        if(isset($_GET['lat']) && isset($_GET['lng']) && isset($_GET['distance'])){
            $query .= " HAVING distanceKM < :distance;";
        }
        $stmt = $this->getDB()->prepare($query);
        $id = $hotel->getId();

        $stmt->bindParam('hotelId', $id, PDO::PARAM_INT);

        if ( isset( $args['surface']['min'] ))
            $stmt->bindParam('surfaceMin', $args['surface']['min'], PDO::PARAM_INT);

        if ( isset( $args['surface']['max'] ))
            $stmt->bindParam('surfaceMax', $args['surface']['max'], PDO::PARAM_INT);

        if ( isset( $args['price']['min'] ) )
            $stmt->bindParam('priceMin', $args['price']['min'], PDO::PARAM_INT);

        if ( isset( $args['price']['max'] ))
            $stmt->bindParam('priceMax', $args['price']['max'], PDO::PARAM_INT);

        if ( isset( $args['rooms'] ))
            $stmt->bindParam('room', $args['rooms'], PDO::PARAM_INT);

        if ( isset( $args['bathRooms'] ) )
            $stmt->bindParam('bathroom', $args['bathRooms'], PDO::PARAM_INT);

        if(isset($_GET['lat']) && isset($_GET['lng']) && isset($_GET['distance'])) {
            $stmt->bindParam('latitude', $_GET['lat'],PDO::PARAM_STR);
            $stmt->bindParam('longitude', $_GET['lng'], PDO::PARAM_STR);
            $stmt->bindParam("distance", $_GET['distance'], PDO::PARAM_INT);
        }

        $stmt->execute();
        $filteredRooms = $stmt->fetchAll();

        // Si aucune chambre ne correspond aux critères, alors on déclenche une exception pour retirer l'hôtel des résultats finaux de la méthode list().
        if ( count( $filteredRooms ) < 1)
            throw new FilterException( "Aucune chambre ne correspond aux critères" );

        $filteredRooms = $filteredRooms[0];
        $cheapestRoom = (new RoomEntity())
            ->setId($filteredRooms['hotelId'])
            ->setTitle($filteredRooms['title'])
            ->setPrice($filteredRooms['prices'])
            ->setCoverImageUrl($filteredRooms['coverImages'])
            ->setBedRoomsCount(intval($filteredRooms['rooms']))
            ->setBathRoomsCount(intval($filteredRooms['bathrooms']))
            ->setSurface(intval($filteredRooms['surfaces']))
            ->setType($filteredRooms['TYPES']);

        $timer->endTimer('getCheapestRoom', $timerId);
        return $cheapestRoom;
    }
  
  
  /**
   * Calcule la distance entre deux coordonnées GPS
   *
   * @param $latitudeFrom
   * @param $longitudeFrom
   * @param $latitudeTo
   * @param $longitudeTo
   *
   * @return float|int
   */
  protected function computeDistance ( $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo ) : float|int {
    return ( 111.111 * rad2deg( acos( min( 1.0, cos( deg2rad( $latitudeTo ) )
          * cos( deg2rad( $latitudeFrom ) )
          * cos( deg2rad( $longitudeTo - $longitudeFrom ) )
          + sin( deg2rad( $latitudeTo ) )
          * sin( deg2rad( $latitudeFrom ) ) ) ) ) );
  }
  
  
  /**
   * Construit une ShopEntity depuis un tableau associatif de données
   *
   * @throws Exception
   */
  protected function convertEntityFromArray ( array $data, array $args ) : HotelEntity {
      $timer = Timers::getInstance();
      $timerId = $timer->startTimer('ConvertEntityFromArray');
    $hotel = ( new HotelEntity() )
      ->setId( $data['ID'] )
      ->setName( $data['display_name'] );
    
    // Charge les données meta de l'hôtel
    $metasData = $this->getMetas( $hotel );
    $hotel->setAddress( $metasData['address'] );
    $hotel->setGeoLat( $metasData['geo_lat'] );
    $hotel->setGeoLng( $metasData['geo_lng'] );
    $hotel->setImageUrl( $metasData['coverImage'] );
    $hotel->setPhone( $metasData['phone'] );
    
    // Définit la note moyenne et le nombre d'avis de l'hôtel
    $reviewsData = $this->getReviews( $hotel );
    $hotel->setRating( $reviewsData['rating'] );
    $hotel->setRatingCount( $reviewsData['count'] );
    
    // Charge la chambre la moins chère de l'hôtel
    $cheapestRoom = $this->getCheapestRoom( $hotel, $args );
    $hotel->setCheapestRoom($cheapestRoom);
    
    // Verification de la distance
    if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
      $hotel->setDistance( $this->computeDistance(
        floatval( $args['lat'] ),
        floatval( $args['lng'] ),
        floatval( $hotel->getGeoLat() ),
        floatval( $hotel->getGeoLng() )
      ) );
      
      if ( $hotel->getDistance() > $args['distance'] )
        throw new FilterException( "L'hôtel est en dehors du rayon de recherche" );
    }
      $timer->endTimer('ConvertEntityFromArray', $timerId);
    return $hotel;
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
  public function list ( array $args = [] ) : array {
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT * FROM wp_users" );
    $stmt->execute();
    
    $results = [];
    foreach ( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
      try {
        $results[] = $this->convertEntityFromArray( $row, $args );
      } catch ( FilterException ) {
        // Des FilterException peuvent être déclenchées pour exclure certains hotels des résultats
      }
    }
    
    
    return $results;
  }
}