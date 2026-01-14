<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Wrapping System' ?></title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= base_url('public/assets/css/dashboard.css') ?>">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= base_url('dashboard') ?>">
            Wrapping Dashboard
        </a>

        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?= ($page ?? '') == 'monitoring' ? 'active' : '' ?>"
                       href="<?= base_url('dashboard') ?>">
                        Monitoring
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($page ?? '') == 'testing' ? 'active' : '' ?>"
                       href="<?= base_url('dashboard/testing') ?>">
                        Testing
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
