<?php
// Proxy to the main application entry point
// This ensures that whether running via 'public/' (local dev) or 'public_html/' (prod),
// we use the SAME logic from ../index.php

require_once __DIR__ . '/../index.php';
