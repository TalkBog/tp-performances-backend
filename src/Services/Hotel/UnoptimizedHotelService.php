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
//SELECT
//add1.meta_value AS address_1,
//add2.meta_value AS address_2,
//add_c.meta_value AS address_city,
//add_z.meta_value AS address_zip,
//add_co.meta_value AS address_country,
//geo_lat.meta_value AS geo_lat,
//geo_lng.meta_value AS geo_lng,
//coverImg.meta_value AS coverImage,
//phone.meta_value AS phone
//
//FROM wp_users AS User
//
//INNER JOIN wp_usermeta AS add1
//ON add1.user_id = User.ID AND add1.meta_key = 'address_1'
//
//INNER JOIN wp_usermeta AS add2
//ON add2.user_id = User.ID AND add1.meta_key = 'address_2'
//
//INNER JOIN wp_usermeta AS add_c
//ON add_c.user_id = User.ID AND add1.meta_key = 'address_city'
//
//INNER JOIN wp_usermeta AS add_z
//ON add_z.user_id = User.ID AND add1.meta_key = 'address_zip'
//
//INNER JOIN wp_usermeta AS add_co
//ON add_co.user_id = User.ID AND add1.meta_key = 'address_country'
//
//INNER JOIN wp_usermeta AS geo_lat
//ON geo_lat.user_id = User.ID AND add1.meta_key = 'geo_lat'
//
//INNER JOIN wp_usermeta AS geo_lng
//ON geo_lng.user_id = User.ID AND add1.meta_key = 'geo_lng'
//
//INNER JOIN wp_usermeta AS coverImg
//ON coverImg.user_id = User.ID AND add1.meta_key = 'coverImage'
//
//INNER JOIN wp_usermeta AS phone
//ON phone.user_id = User.ID AND add1.meta_key = 'phone';
  protected function getMetas ( HotelEntity $hotel ) : array {
      $timer = Timers::getInstance();
      $timerId = $timer->startTimer('getMetas');
    $metaDatas = [
      'address' => [
        'address_1' => $this->getMeta( $hotel->getId(), 'address_1' ),
        'address_2' => $this->getMeta( $hotel->getId(), 'address_2' ),
        'address_city' => $this->getMeta( $hotel->getId(), 'address_city' ),
        'address_zip' => $this->getMeta( $hotel->getId(), 'address_zip' ),
        'address_country' => $this->getMeta( $hotel->getId(), 'address_country' ),
      ],
      'geo_lat' =>  $this->getMeta( $hotel->getId(), 'geo_lat' ),
      'geo_lng' =>  $this->getMeta( $hotel->getId(), 'geo_lng' ),
      'coverImage' =>  $this->getMeta( $hotel->getId(), 'coverImage' ),
      'phone' =>  $this->getMeta( $hotel->getId(), 'phone' ),
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
      // SELECT * FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
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
    $query = "SELECT post.ID as ID,
                post.post_title as title, 
                MIN(CAST(price.meta_value as FLOAT)) AS price, 
                coverImage.meta_value as coverImage, 
                CAST(room.meta_value as INTEGER) AS room, 
                CAST(bathroom.meta_value as INTEGER) AS bathroom, 
                CAST(surface.meta_value as INTEGER) AS surface, 
                type.meta_value AS type 

                FROM wp_posts AS post 
                    
                INNER JOIN wp_postmeta AS price ON price.post_id = post.ID AND price.meta_key = 'price' 
                INNER JOIN wp_postmeta AS coverImage ON coverImage.post_id = post.ID AND coverImage.meta_key = 'coverImage' 
                INNER JOIN wp_postmeta AS room ON room.post_id = post.ID AND room.meta_key = 'bedrooms_count' 
                INNER JOIN wp_postmeta AS bathroom ON bathroom.post_id = post.ID AND bathroom.meta_key = 'bathrooms_count' 
                INNER JOIN wp_postmeta AS surface ON surface.post_id = post.ID AND surface.meta_key = 'surface' 
                INNER JOIN wp_postmeta AS type ON type.post_id = post.ID AND type.meta_key = 'type' 
                
                WHERE post.post_author = :hotelId ";

    $whereClauses = [];

    if ( isset( $args['surface']['min'] ))
        $whereClauses[] = 'surface >= :surfaceMin';

    if ( isset( $args['surface']['max'] ))
        $whereClauses[] = 'surface <= :surfaceMax';

    if ( isset( $args['price']['min'] ) )
        $whereClauses[] = 'price >= :priceMin';

    if ( isset( $args['price']['max'] ))
        $whereClauses[] = 'price <= :priceMax';

    if ( isset( $args['rooms'] ))
        $whereClauses[] = 'room = :room';

    if ( isset( $args['bathRooms'] ) )
        $whereClauses[] = 'bathroom = :bathroom';

    if ( isset( $args['types'] ) && ! empty( $args['types'] ) )
        $whereClauses[] = 'type = :type';

    if(count($whereClauses) > 0){
        $query .= ' AND ' .implode(' AND ', $whereClauses);
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

      if ( isset( $args['types'] ) && ! empty( $args['types'] ) )
          $stmt->bindParam('type', $args['types']);

      dump($stmt);
      die();
    $stmt->execute();


    $filteredRooms = $stmt->fetchAll();

    // Si aucune chambre ne correspond aux critères, alors on déclenche une exception pour retirer l'hôtel des résultats finaux de la méthode list().
    if ( count( $filteredRooms ) < 1 )
      throw new FilterException( "Aucune chambre ne correspond aux critères" );
    
    $filteredRooms = $filteredRooms[0];
    $cheapestRoom = (new RoomEntity())
        ->setId($filteredRooms[0])
        ->setTitle($filteredRooms[1])
        ->setPrice($filteredRooms[2])
        ->setCoverImageUrl(strval($filteredRooms[3]))
        ->setBedRoomsCount($filteredRooms[4])
        ->setBathRoomsCount($filteredRooms[5])
        ->setSurface($filteredRooms[6])
        ->setType($filteredRooms[7]);


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