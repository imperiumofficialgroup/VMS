<?php
session_start();
include '../auth/db.php';

if (!isset($_SESSION['volunteer_id'])) {
    header("Location: ../login.php");
    exit;
}

$volunteer_id = $_SESSION['volunteer_id'];

// Fetch all events sorted by date descending
$events = $conn->query("SELECT * FROM events ORDER BY event_date DESC");

// Categorize events
$upcoming_events = [];
$completed_events = [];
$today = date('Y-m-d');

while ($event = $events->fetch_assoc()) {
    if ($event['event_date'] >= $today) {
        $upcoming_events[] = $event;
    } else {
        $completed_events[] = $event;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events | VMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#5DADE2',
                        accent: '#A569BD',
                        background: '#FBFCFC',
                        text: '#1C2833',
                        subtle: '#D5D8DC',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FBFCFC;
            color: #1C2833;
        }

        .event-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #D5D8DC;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: #5DADE2;
        }

        .event-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .date-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }

        .upcoming-badge {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
        }

        .completed-badge {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .upcoming-status {
            background: rgba(93, 173, 226, 0.1);
            color: #5DADE2;
        }

        .completed-status {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }

        .register-btn {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-align: center;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
        }

        .completed-tag {
            background: linear-gradient(135deg, #FBFCFC 0%, #f8fafc 100%);
            color: #64748b;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 600;
            text-align: center;
            border: 1px solid #D5D8DC;
        }

        .line-clamp-3 {
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .empty-state {
            background: linear-gradient(135deg, #FBFCFC 0%, #f8fafc 100%);
            border: 2px dashed #D5D8DC;
            border-radius: 16px;
        }

        /* Mobile First Responsive Design */
        @media (max-width: 640px) {
            .page-container {
                padding: 1rem;
            }

            .event-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .event-image {
                height: 160px;
            }
        }

        @media (min-width: 641px) and (max-width: 768px) {
            .page-container {
                padding: 1.5rem;
            }

            .event-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }
        }

        @media (min-width: 769px) {
            .page-container {
                padding: 2rem;
                width: calc(100% - 16rem);
                margin-left: 16rem;
                max-width: 1400px;
            }

            .event-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 2rem;
            }
        }

        .section-title {
            color: #1C2833;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .event-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="page-container lg:ml-72">
        <!-- Header Section -->
        <div class="header-section mt-12 sm:mt-0 mb-8 text-center sm:text-left">
            <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Events & Activities</h1>
            <p class="text-text/70">Discover upcoming opportunities and review past engagements</p>
        </div>

        <!-- Upcoming Events -->
    <?php if(!empty($upcoming_events)): ?>
    <section class="mb-12">
        <h2 class="section-title"><i class="fas fa-calendar-alt text-primary"></i> Upcoming Events</h2>
        <div class="event-grid grid">
            <?php foreach($upcoming_events as $event):
                $today = date('Y-m-d');
                $show_register = !empty($event['registration_link']) && $today < $event['event_date'];
            ?>
            <div class="event-card">
                <div class="relative">
                    <img src="../uploads/<?= htmlspecialchars($event['image_path']) ?>" class="event-image" alt="<?= htmlspecialchars($event['title']) ?>">
                    <span class="date-badge upcoming-badge"><i class="fas fa-calendar mr-1"></i><?= date('M j, Y', strtotime($event['event_date'])) ?></span>
                </div>
                <div class="p-5 flex flex-col flex-grow">
                    <div class="flex items-start justify-between mb-3">
                        <h3 class="text-lg font-semibold text-text flex-1 pr-2"><?= htmlspecialchars($event['title']) ?></h3>
                        <span class="status-badge upcoming-status">Upcoming</span>
                    </div>
                    <p class="text-text/70 text-sm mb-4 flex-grow line-clamp-3"><?= htmlspecialchars($event['description']) ?></p>
                    <div class="space-y-2 mb-4">
                        <div class="event-detail">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                            <span><?= htmlspecialchars($event['location']) ?></span>
                            <?php if(!empty($event['location_link'])): ?>
                                &nbsp;|&nbsp;<a href="<?= htmlspecialchars($event['location_link']) ?>" target="_blank" class="text-primary underline text-sm">View on Map</a>
                            <?php endif; ?>
                        </div>
                        <div class="event-detail"><i class="far fa-clock text-primary"></i><span><?= date('g:i A', strtotime($event['event_date'])) ?></span></div>
                    </div>

                    <!-- Registration Button -->
                    <?php if($show_register): ?>
                        <a href="<?= htmlspecialchars($event['registration_link']) ?>" target="_blank" class="register-btn inline-flex items-center justify-center">
                            <i class="fas fa-external-link-alt mr-2"></i> Register Now
                        </a>
                    <?php else: ?>
                        <div class="text-center text-text/50 text-sm py-2">Registration details coming soon</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php else: ?>
    <section class="mb-12">
        <h2 class="section-title"><i class="fas fa-calendar-alt text-primary"></i> Upcoming Events</h2>
        <div class="empty-state p-8 text-center">
            <i class="fas fa-calendar-plus text-4xl text-text/30 mb-3"></i>
            <h3 class="text-lg font-semibold text-text mb-2">No Upcoming Events</h3>
            <p class="text-text/60">Check back later for new volunteer opportunities</p>
        </div>
    </section>
    <?php endif; ?>

        <!-- Completed Events -->
    <?php if(!empty($completed_events)): ?>
    <section class="mb-12">
        <h2 class="section-title"><i class="fas fa-check-circle text-green-600"></i> Completed Events</h2>
        <div class="event-grid grid">
            <?php foreach($completed_events as $event): ?>
            <div class="event-card">
                <div class="relative">
                    <img src="../uploads/<?= htmlspecialchars($event['image_path']) ?>" class="event-image" alt="<?= htmlspecialchars($event['title']) ?>">
                    <span class="date-badge completed-badge"><i class="fas fa-check mr-1"></i><?= date('M j, Y', strtotime($event['event_date'])) ?></span>
                </div>
                <div class="p-5 flex flex-col flex-grow">
                    <div class="flex items-start justify-between mb-3">
                        <h3 class="text-lg font-semibold text-text flex-1 pr-2"><?= htmlspecialchars($event['title']) ?></h3>
                        <span class="status-badge completed-status">Completed</span>
                    </div>
                    <p class="text-text/70 text-sm mb-4 flex-grow line-clamp-3"><?= htmlspecialchars($event['description']) ?></p>
                    <div class="space-y-2 mb-4">
                        <div class="event-detail">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                            <span><?= htmlspecialchars($event['location']) ?></span>
                            <?php if(!empty($event['location_link'])): ?>
                                &nbsp;|&nbsp;<a href="<?= htmlspecialchars($event['location_link']) ?>" target="_blank" class="text-primary underline text-sm">View on Map</a>
                            <?php endif; ?>
                        </div>
                        <div class="event-detail"><i class="far fa-clock text-green-600"></i><span><?= date('g:i A', strtotime($event['event_date'])) ?></span></div>
                    </div>
                    <div class="completed-tag"><i class="fas fa-flag-checkered mr-2"></i>Event Completed</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php else: ?>
    <section class="mb-12">
        <h2 class="section-title"><i class="fas fa-check-circle text-green-600"></i> Completed Events</h2>
        <div class="empty-state p-8 text-center">
            <i class="fas fa-history text-4xl text-text/30 mb-3"></i>
            <h3 class="text-lg font-semibold text-text mb-2">No Completed Events</h3>
            <p class="text-text/60">Your completed events will appear here after participation</p>
        </div>
    </section>
    <?php endif; ?>
    </div>
</body>
</html>