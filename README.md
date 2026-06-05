A lightweight, high-performance WordPress plugin designed to seamlessly embed a customizable audio streaming player into any WordPress website.

Built with modular CSS architecture, dynamic JavaScript audio event handling, and a user-friendly WordPress administration interface, this plugin delivers smooth and reliable live audio streaming experiences.

Author: Mahesh Kumar
Website: https://maheshkumarm.com

📁 Repository Structure

The plugin is packaged as a single installable WordPress plugin. The internal structure is organized as follows:

radio-player/
├── css/
│   └── style_v10.css
├── images/
│   └── default-admin-logo.png
├── includes/
│   └── need_help.php
├── js/
│   └── script_v10.js
├── README.txt
└── yemcoders-radio-player.php
🛠️ Features Overview
📻 Core Audio Streaming Engine

The plugin is built using native HTML5 audio capabilities, ensuring stable and lightweight streaming performance without dependency on Flash or external heavy libraries.

Native HTML5 audio playback for real-time streaming
Compatible with Icecast, Shoutcast, and direct stream URLs
Optimized buffering system for stable playback
Automatic recovery handling for interrupted streams
Lightweight architecture for fast load times
🎨 Advanced UI / UX System (v10 Layout)

The UI is designed for responsiveness, scalability, and theme independence.

Fully responsive grid system for desktop and mobile
Clean card-based station layout
Smooth play/pause state transitions
Loading and buffering indicators
Modular CSS architecture for safe theme compatibility
Isolated styling system to prevent CSS conflicts
⚙️ WordPress Admin Dashboard

The plugin includes a powerful admin panel for managing stations and settings.

Add, edit, and manage radio stations easily
Upload station logos directly from media library
Organize stations by country and language
Enable featured/pinned stations
Configure default station settings
Control grid layout and pagination options
Built-in help documentation inside dashboard
🔗 Shortcode System

Easily embed the player anywhere in your WordPress site:

[yemcoders_radio_player]
Filters:
[yemcoders_radio_player language="English"]
[yemcoders_radio_player country="USA"]
[yemcoders_radio_player language="Spanish" country="Spain"]
🚀 Installation Guide
Option 1: WordPress Dashboard (Recommended)
Download radio-player.zip
Go to WordPress Admin → Plugins → Add New
Click Upload Plugin
Select the ZIP file
Click Install Now
Activate Plugin
Option 2: Manual Upload (FTP)
Extract radio-player.zip
Connect to your server via FTP
Navigate to /wp-content/plugins/
Upload radio-player folder
Activate plugin from WordPress dashboard
💻 Developer Guide
JavaScript Architecture
js/script_v10.js handles:
Audio playback control
Play/pause states
Station switching logic
UI synchronization

⚠️ Ensure proper cleanup of event listeners to prevent memory leaks.

CSS System
css/style_v10.css uses a fully namespaced structure
Prevents theme style conflicts
Safe for Elementor, WPBakery, and custom themes
Mobile-first responsive design
PHP Core System
Main plugin file handles:
WordPress hooks registration
Shortcode rendering
Admin settings integration
AJAX station management
User favorites system
🤝 Contribution Guide
Fork the repository

Create a new branch:

git checkout -b feature-name

Commit changes:

git commit -m "Add feature or fix"

Push branch:

git push origin feature-name
Open Pull Request
📄 License

Developed and maintained by Mahesh Kumar
Under the YemCoders project ecosystem.

For support, customization, or portfolio inquiries:
👉 https://maheshkumarm.com
