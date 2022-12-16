Vous pouvez utiliser ce [GSheets](https://docs.google.com/spreadsheets/d/13Hw27U3CsoWGKJ-qDAunW9Kcmqe9ng8FROmZaLROU5c/copy?usp=sharing) pour suivre l'évolution de l'amélioration de vos performances au cours du TP 

## Question 2 : Utilisation Server Timing API

**Temps de chargement initial de la page** : TEMPS

**Choix des méthodes à analyser** :

- `ConverEntityFromArray` 29.71s
- `getCheapestRoom` 16.40s
- `getMetas` 4.25s



## Question 3 : Réduction du nombre de connexions PDO

**Temps de chargement de la page** : 29.8s

**Temps consommé par `getDB()`** 

- **Avant** 1.29s

- **Après** 2.76 ms


## Question 4 : Délégation des opérations de filtrage à la base de données

**Temps de chargement globaux** 

- **Avant** TEMPS

- **Après** TEMPS


#### Amélioration de la méthode `getMeta` et donc de la méthode `getMetas` :

- **Avant** TEMPS

```sql
SELECT * FROM wp_usermeta
```

- **Après** TEMPS

```sql
SELECT * FROM wp_usermeta WHERE user_id = :userId AND meta_key = :key
```



#### Amélioration de la méthode `METHOD` :

- **Avant** TEMPS

```sql
SELECT * FROM wp_posts WHERE post_author = :hotelId AND post_type = 'room'
```

- **Après** 12.50s

```sql
-- NOUVELLE REQ SQL
```



#### Amélioration de la méthode `METHOD` :

- **Avant** TEMPS

```sql
-- REQ SQL DE BASE
```

- **Après** TEMPS

```sql
-- NOUVELLE REQ SQL
```



## Question 5 : Réduction du nombre de requêtes SQL pour `getMetas()`

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 2201      | 601       |
 | Temps de `getMetas()`        | 1.58 s    | 232.43 ms |

## Question 6 : Création d'un service basé sur une seule requête SQL

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 601       | 1         |
| Temps de chargement global   | 25.5 s    | 4.82 s    |

**Requête SQL**

```SQL
SELECT
    USER.ID AS user_id,
    USER.display_name AS user_name,
    USER.user_email AS email,
    address1.meta_value AS address_1,
    address2.meta_value AS address_2,
    addressCity.meta_value AS address_city,
    addressZip.meta_value AS address_zip,
    addressCountry.meta_value AS address_country,
    phone.meta_value AS phone,
    lat.meta_value AS latitude,
    lng.meta_value AS longitude,
    111.111 * DEGREES(
            ACOS(
                    LEAST(
                            1.0,
                            COS(RADIANS(lat.meta_value)) * COS(RADIANS(46.988708)) * COS(
                                    RADIANS(lng.meta_value - 3.160778)
                             ) + SIN(RADIANS(lat.meta_value)) * SIN(RADIANS(46.988708))
                     )
             )
     ) AS distanceKM,
    coverImage.meta_value AS coverImage,
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
    ON USER.ID = address1.user_id AND address1.meta_key = "address_1"

INNER JOIN wp_usermeta AS address2
    ON USER.ID = address2.user_id AND address2.meta_key = "address_2"

INNER JOIN wp_usermeta AS addressCity
    ON USER.ID = addressCity.user_id AND addressCity.meta_key = "address_city"

INNER JOIN wp_usermeta AS addressZip
    ON USER.ID = addressZip.user_id AND addressZip.meta_key = "address_zip"

INNER JOIN wp_usermeta AS addressCountry
    ON USER.ID = addressCountry.user_id AND addressCountry.meta_key = "address_country"

INNER JOIN wp_usermeta AS phone
    ON USER.ID = phone.user_id AND phone.meta_key = "phone"

INNER JOIN wp_usermeta AS lat
    ON USER.ID = lat.user_id AND lat.meta_key = "geo_lat"

INNER JOIN wp_usermeta AS lng
    ON USER.ID = lng.user_id AND lng.meta_key = "geo_lng"

INNER JOIN wp_usermeta AS coverImage
    ON USER.ID = coverImage.user_id AND coverImage.meta_key = "coverImage"

INNER JOIN wp_postmeta AS rating
    ON post.ID = rating.post_id AND rating.meta_key = "rating" AND post.post_type = "review"

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
        post.post_type = "room"
        AND price.meta_value >= 200
        AND price.meta_value <= 230
        AND room.meta_value >= 5
        AND bathroom.meta_value >= 5
        AND surface.meta_value >= 130
        AND surface.meta_value <= 150
        AND TYPE.meta_value IN ("Maison", "Appartement")

    GROUP BY post.post_author
    ) AS cheapestRoom
        ON USER.ID = cheapestRoom.post_author

GROUP BY USER.ID
HAVING distanceKM <= 30;
```

## Question 7 : ajout d'indexes SQL

**Indexes ajoutés**

- `wp_posts` : `post_author`
- `wp_usermeta` : `meta_value`
- `wp_postmeta` : `meta_value`

**Requête SQL d'ajout des indexes** 

```sql
CREATE UNIQUE INDEX post_author ON wp_posts (post_author, ID);
CREATE UNIQUE INDEX Meta_value ON wp_usermeta (user_id, meta_key, meta_value);
CREATE UNIQUE INDEX Meta_value ON wp_postmeta (post_id, meta_key, meta_value);
```

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `UnoptimizedService`           | 1.25 s      | 0.69 s       |
| `OneRequestService`            | 1.08 s      | 0.64 s       |
[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)




## Question 8 : restructuration des tables

**Temps de chargement de la page**

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `OneRequestService`            | TEMPS       | TEMPS        |
| `ReworkedHotelService`         | TEMPS       | TEMPS        |

[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)

### Table `hotels` (200 lignes)

```SQL
CREATE TABLE `hotels` (
                       `id` int(11) NOT NULL AUTO_INCREMENT,
                       `name` varchar(255) NOT NULL,
                       `mail` varchar(255) NOT NULL,
                       `address_1` varchar(255) NOT NULL,
                       `address_2` varchar(255) NOT NULL,
                       `address_city` varchar(255) NOT NULL,
                       `address_zip` varchar(255) NOT NULL,
                       `address_country` varchar(255) NOT NULL,
                       `phone` varchar(255) NOT NULL,
                       `lat` varchar(255) NOT NULL,
                       `lng` varchar(255) NOT NULL,
                       `coverImage` varchar(255) NOT NULL,
                       PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
```

```SQL
INSERT INTO hotels (
 SELECT
  USER.ID AS id,
  USER.display_name AS name,
  USER.user_email AS mail,
  address1.meta_value AS address_1,
  address2.meta_value AS address_2,
  addressCity.meta_value AS address_city,
  addressZip.meta_value AS address_zip,
  addressCountry.meta_value AS address_country,
  phone.meta_value AS phone,
  lat.meta_value AS lat,
  lng.meta_value AS lng,
  coverImage.meta_value AS coverImage

 FROM wp_users AS USER
    
    INNER JOIN wp_usermeta AS address1
 ON USER.ID = address1.user_id AND address1.meta_key = "address_1"

  INNER JOIN wp_usermeta AS address2
  ON USER.ID = address2.user_id AND address2.meta_key = "address_2"

  INNER JOIN wp_usermeta AS addressCity
  ON USER.ID = addressCity.user_id AND addressCity.meta_key = "address_city"

  INNER JOIN wp_usermeta AS addressZip
  ON USER.ID = addressZip.user_id AND addressZip.meta_key = "address_zip"

  INNER JOIN wp_usermeta AS addressCountry
  ON USER.ID = addressCountry.user_id AND addressCountry.meta_key = "address_country"

  INNER JOIN wp_usermeta AS phone
  ON USER.ID = phone.user_id AND phone.meta_key = "phone"

  INNER JOIN wp_usermeta AS lat
  ON USER.ID = lat.user_id AND lat.meta_key = "geo_lat"

  INNER JOIN wp_usermeta AS lng
  ON USER.ID = lng.user_id AND lng.meta_key = "geo_lng"

  INNER JOIN wp_usermeta AS coverImage
  ON USER.ID = coverImage.user_id AND coverImage.meta_key = "coverImage"

 GROUP BY USER.ID)
```

### Table `rooms` (1 200 lignes)

```SQL
CREATE TABLE `rooms` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `id_hotel` int(11) NOT NULL,
                      `title` varchar(255) NOT NULL,
                      `price` varchar(255) NOT NULL,
                      `surface` varchar(255) NOT NULL,
                      `room` varchar(255) NOT NULL,
                      `bathroom` varchar(255) NOT NULL,
                      `coverImage` varchar(255) NOT NULL,
                      `type` varchar(255) NOT NULL,
                      PRIMARY KEY (`id`),
                      KEY `FK_id_hotel` (`id_hotel`),
                      CONSTRAINT `FK_id_hotel` FOREIGN KEY (`id_hotel`) REFERENCES `hotels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
```

```SQL
-- Ne fonctionne pas #1366 - Incorrect integer value: 'https://source.unsplash.com/category/objects/640x480' for column `tp`.`rooms`.`id_hotel` at row 1
INSERT INTO rooms (
 SELECT

  bathroom.meta_value AS bathroom,
  coverImage.meta_value AS coverImage,
  post.ID AS id,
  post.post_author AS id_hotel,
  price.meta_value AS price,
  room.meta_value AS room,
  surface.meta_value AS surface,
  post.post_title AS title,
  TYPE.meta_value AS type

 FROM wp_posts AS post

       INNER JOIN wp_postmeta AS price
                  ON price.post_id = post.ID AND price.meta_key = 'price'

       INNER JOIN wp_postmeta AS room
                  ON room.post_id = post.ID AND room.meta_key = 'bedrooms_count'

       INNER JOIN wp_postmeta AS bathroom
                  ON bathroom.post_id = post.ID AND bathroom.meta_key = 'bathrooms_count'

       INNER JOIN wp_postmeta AS surface
                  ON surface.post_id = post.ID AND surface.meta_key = 'surface'

       INNER JOIN wp_postmeta AS TYPE
                  ON TYPE.post_id = post.ID AND TYPE.meta_key = 'type'

       INNER JOIN wp_postmeta AS coverImage
                  ON coverImage.post_id = post.ID AND coverImage.meta_key = 'coverImage'

 GROUP BY post.post_author)
```

### Table `reviews` (19 700 lignes)

```SQL
CREATE TABLE `reviews` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `id_post` int(11) NOT NULL,
                        `rating` int(11) NOT NULL,
                        PRIMARY KEY (`id`),
                        KEY `FK_id_post` (`id_post`),
                        CONSTRAINT `FK_id_post` FOREIGN KEY (`id_post`) REFERENCES `rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
```

```SQL
-- ne fonctionne pas #1452 - Cannot add or update a child row: a foreign key constraint fails (`tp`.`reviews`, CONSTRAINT `FK_id_post` FOREIGN KEY (`id_post`) REFERENCES `rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE)
INSERT INTO `reviews`(
 SELECT
  rating.meta_id AS id,
  rating.post_id AS id_post,
  rating.meta_value AS rating

 FROM wp_postmeta AS rating

 WHERE rating.meta_key = "rating")
```


## Question 13 : Implémentation d'un cache Redis

**Temps de chargement de la page**

| Sans Cache | Avec Cache |
|------------|------------|
| TEMPS      | TEMPS      |
[URL pour ignorer le cache sur localhost](http://localhost?skip_cache)

## Question 14 : Compression GZIP

**Comparaison des poids de fichier avec et sans compression GZIP**

|                       | Sans  | Avec  |
|-----------------------|-------|-------|
| Total des fichiers JS | POIDS | POIDS |
| `lodash.js`           | POIDS | POIDS |

## Question 15 : Cache HTTP fichiers statiques

**Poids transféré de la page**

- **Avant** : POIDS
- **Après** : POIDS

## Question 17 : Cache NGINX

**Temps de chargement cache FastCGI**

- **Avant** : TEMPS
- **Après** : TEMPS

#### Que se passe-t-il si on actualise la page après avoir coupé la base de données ?

REPONSE

#### Pourquoi ?

REPONSE
