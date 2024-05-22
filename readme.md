# Spanish Postal Codes API

This Symfony-based API provides endpoints for querying various postal codes in Spain.

## Description

The Spanish Postal Codes API is built using Symfony, offering a simple and efficient way to retrieve information about postal codes across different regions of Spain. It allows users to query the API to obtain details such as provinces, towns, and postal codes associated with specific locations within Spain.

## Endpoints

- `/api/locations`: Retrieve information about all locations in Spain.
  - **Method:** GET
  - **Response:** JSON array containing details of all locations.

- `/api/locations/{codPostal}`: Retrieve information about locations in Spain based on the provided postal code.
  - **Method:** GET
  - **Parameters:**
    - `{codPostal}`: The postal code to search for.
  - **Response:** JSON array containing details of all locations matching the provided postal code.

## Usage

To use the Spanish Postal Codes API, you can send HTTP requests to the provided endpoints using any HTTP client or web browser. Below are some examples of how you can interact with the API:

### Retrieve all locations:

Send a GET request to `/api/locations` endpoint to retrieve information about all locations in Spain.

Example using cURL:

```
curl -X GET http://your-api-domain.com/api/locations
```

...

### Retrieve locations by postal code:

Send a GET request to `/api/locations/{codPostal}` endpoint, replacing `{codPostal}` with the desired postal code, to retrieve information about locations in Spain based on the provided postal code.

Example using cURL:
```
curl -X GET http://your-api-domain.com/api/locations/28001
```

## Data

Data files in CSV format are available in the `data` folder.

## Installation

To install and run the Spanish Postal Codes API on your local machine or server, follow these steps:

1. Clone the repository:
   
    ```
    git clone https://github.com/your/repository.git
    ```
2. Install dependencies using Composer:
    ```
    composer install
    ```
3. Configure your database connection in the `.env` file.

4. Create the database schema:

    ```
    php bin/console doctrine:database:create
    ```
    ```
    php bin/console doctrine:schema:update --force
    ```
5. Start the Symfony server:
    ```
    php bin/console server:start
    ```


6. You can now access the API at `http://localhost:8000`.

## Contributing

Contributions to the Spanish Postal Codes API are welcome! If you'd like to contribute, please follow these steps:

1. Fork the repository.

2. Create a new branch for your feature or bug fix.

## License

This project is licensed under the [MIT License](https://opensource.org/licenses/MIT). You are free to use, modify, and distribute this software as permitted by the license. See the `LICENSE` file for more details.
