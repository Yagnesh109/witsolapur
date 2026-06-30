# Walchand Institute of Technology (WIT) — Website

Static college website built with HTML, CSS, and JavaScript. Served locally via XAMPP.

## Project structure

```
WIT/
├── index.html              # Homepage (entry point)
├── README.md
├── assets/
│   ├── css/
│   │   ├── main.css        # Common layout and navbar styles
│   │   └── branch.css      # Unified department page styles
│   ├── js/
│   │   ├── main.js         # Homepage interactions
│   │   └── department.js   # Department page interactions
│   ├── images/
│   │   ├── brand/          # Logos
│   │   ├── campus/         # Campus photos
│   │   ├── departments/    # Department thumbnails
│   │   ├── faculty/        # Faculty photos
│   │   └── achievements/   # Achievement card images
│   └── documents/          # PDFs and downloads
│       ├── profiles/
│       ├── labs/
│       ├── syllabus/
│       └── achievements/
└── pages/
    └── cse.html            # CSE department page
```

## Local development

1. Place the project under `xampp/htdocs/Projects/WIT`.
2. Start Apache in XAMPP.
3. Open `http://localhost/Projects/WIT/` for the homepage.
4. Open `http://localhost/Projects/WIT/pages/cse.html` for the CSE department page.

## Adding content

- **Homepage**: edit `index.html`, `assets/css/main.css`, and `assets/js/main.js`.
- **Department page**: edit `pages/cse.html` and related assets under `assets/`.
- **PDFs**: add files under `assets/documents/` in the matching subfolder.
