# Hit Counter PHP Application

This application is a simple PHP-based hit counter that tracks and displays the number of hits for specific URLs. It also supports generating charts in multiple formats to visualize the data.

## Features
- Tracks hits for specific URLs.
- Generates charts in the following formats:
  - Live (SVG) using `ChartJS-PHP`
  - PNG using `jpgraph`
  - SVG using `Maantje Charts`
- Lists all saved directories with their hit counts and links.
- Accessible only with a secret key for secure operations.

## Setup

1. Clone the repository and navigate to the project directory:
   ```bash
   git clone <repository-url>
   cd hit-counter-php
   ```

2. Install dependencies using Composer:
   ```bash
   composer install
   ```

3. Configure the `.env` file with your database credentials and secret key:
   ```env
   DB_HOST=localhost
   DB_DATABASE=hits
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   DB_CHARSET=utf8mb4
   SECRET_KEY=your_secret_key
   ```

4. Set up the database using the `setup-db` target in the Makefile:
   ```bash
   make setup-db
   ```

5. Start a local PHP server for testing:
   ```bash
   php -S localhost:8000 -t app
   ```

## Usage

### Tracking Hits
To track hits for a specific URL, make a GET request with the `url` parameter:
```bash
curl "http://localhost:8000/index.php?url=example.com"
```

### Generating Charts
To generate charts, use the `chart` and `chart_type` parameters:

- **Live Chart (SVG):**
  ```bash
  curl "http://localhost:8000/index.php?url=example.com&chart=true&chart_type=live"
  ```

- **PNG Chart:**
  ```bash
  curl "http://localhost:8000/index.php?url=example.com&chart=true&chart_type=png"
  ```

- **SVG Chart (Maantje):**
  ```bash
  curl "http://localhost:8000/index.php?url=example.com&chart=true&chart_type=svg"
  ```

### Customizing the Title
You can customize the title text displayed in the SVG counter by using the `title` parameter in the URL. If no title is provided, the default is an eye emoji (👀).

Example:
```bash
curl "http://localhost:8000/index.php?url=example.com&title=CustomTitle"
```

### Formatting Hit Counts
Large hit counts are automatically formatted for readability. For example:
- `1500` is displayed as `1.5 k`
- `300000` is displayed as `300 k`
- `1500000` is displayed as `1.5 M`

### Customizing the Counter Appearance
You can customize the background colors and title text of the SVG counter using the following parameters:

- **`count_bg`**: Sets the background color of the hit count section (default: `#79C83D`).
- **`title_bg`**: Sets the background color of the title section (default: `#555555`).
- **`title`**: Sets the title text (default: 👀).

Example:
```bash
curl "http://localhost:8000/index.php?url=example.com&count_bg=%23FF5733&title_bg=%23000000&title=Hits"
```

### Listing Saved Directories
To list all saved directories with their hit counts and links, use the `list` parameter with the secret key:
```bash
curl "http://localhost:8000/index.php?list=your_secret_key"
```

## Example Output

### Saved Directories
```html
<html>
<head><title>Saved Directories</title></head>
<body>
<h1>Saved Directories</h1>
<table border="1">
    <tr>
        <th>URL</th>
        <th>Hits</th>
        <th>Hit Link</th>
        <th>Chart Links</th>
    </tr>
    <tr>
        <td>example.com</td>
        <td>42</td>
        <td><a href="?url=example.com">Hit Link</a></td>
        <td>
            <a href="?url=example.com&chart=true&chart_type=live">Live Chart</a> |
            <a href="?url=example.com&chart=true&chart_type=png">PNG Chart</a> |
            <a href="?url=example.com&chart=true&chart_type=svg">SVG Chart</a>
        </td>
    </tr>
</table>
</body>
</html>
```

## License
This project is licensed under the MIT License. See the `LICENSE` file for details.