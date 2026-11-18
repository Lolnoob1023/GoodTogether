
<?php
// GOOD TOGETHER - ABOUT US PAGE

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require "database.php";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>About Us | Good Together</title>

    <link rel="stylesheet" href="style.css">

    <style>
        /* About page styles only */

        .gt-about {
            padding: 35px;
            background: #fffdf8;
        }

        .gt-about-hero {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 35px;
            align-items: center;
            margin-bottom: 55px;
        }

        .gt-about-eyebrow {
            color: #67826d;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .gt-about h2 {
            font-family: Georgia, serif;
            color: #344b3c;
            font-size: 38px;
            line-height: 1.2;
            margin: 15px 0;
        }

        .gt-about h3 {
            color: #344b3c;
            font-family: Georgia, serif;
            font-size: 24px;
        }

        .gt-about p {
            color: #536257;
            line-height: 1.8;
        }

        .gt-about-hero img {
            width: 100%;
            height: 350px;
            object-fit: cover;
            border-radius: 6px;
        }

        .gt-about-button {
            display: inline-block;
            background: #344b3c;
            color: white;
            padding: 13px 22px;
            margin-top: 15px;
            font-weight: bold;
            text-decoration: none;
            border-radius: 4px;
        }

        .gt-about-button:hover {
            background: #496b53;
        }

        .gt-about-heading {
            text-align: center;
            margin-bottom: 25px;
        }

        .gt-about-heading h2 {
            font-size: 32px;
        }

        .gt-about-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 55px;
        }

        .gt-about-card {
            background: #f6f0e3;
            border: 1px solid #e0d9cc;
            border-radius: 5px;
            overflow: hidden;
        }

        .gt-about-card img {
            width: 100%;
            height: 240px;
            object-fit: cover;
            display: block;
        }

        .gt-about-card-body {
            padding: 22px;
        }

        .gt-about-steps {
            background: #f6f0e3;
            padding: 30px;
            border-radius: 5px;
            margin-bottom: 30px;
        }

        .gt-steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .gt-step-number {
            color: #52715a;
            font-size: 30px;
            font-weight: bold;
        }

        .gt-about-cta {
            text-align: center;
            padding: 30px 15px;
        }

        @media (max-width: 750px) {
            .gt-about {
                padding: 20px;
            }

            .gt-about-hero,
            .gt-about-cards,
            .gt-steps-grid {
                grid-template-columns: 1fr;
            }

            .gt-about h2 {
                font-size: 29px;
            }

            .gt-about-hero img {
                height: 260px;
            }
        }
    </style>
</head>

<body>

<div class="page">

    <header>
        <h1>Good Together</h1>
        <p>People helping people, close to home.</p>
    </header>

    <?php require "navigation.php"; ?>

    <main class="gt-about">

        <!-- INTRODUCTION -->

        <section class="gt-about-hero">

            <div>
                <p class="gt-about-eyebrow">
                    About Good Together
                </p>

                <h2>
                    Small actions.
                    Stronger communities.
                </h2>

                <p>
                    Good Together is a volunteering
                    platform designed to connect people
                    with meaningful opportunities
                    in their local communities.
                </p>

                <p>
                    We believe everyone can make a
                    difference. Whether it is cleaning
                    a park, preparing food donations
                    or supporting people in need,
                    every contribution matters.
                </p>

                <a href="opportunities.php"
                   class="gt-about-button">
                    Explore Opportunities
                </a>
            </div>

            <img
                src="images/cleanup.jpg"
                alt="Volunteers cleaning a park together"
            >

        </section>


        <!-- ACTIVITIES -->

        <section>

            <div class="gt-about-heading">
                <p class="gt-about-eyebrow">
                    Community Activities
                </p>

                <h2>
                    Different ways to give back
                </h2>

                <p>
                    Discover how volunteering can
                    help create stronger communities.
                </p>
            </div>

            <div class="gt-about-cards">

                <article class="gt-about-card">

                    <img
                        src="images/food-donation.jpeg"
                        alt="Volunteers preparing food donations"
                        loading="lazy"
                    >

                    <div class="gt-about-card-body">

                        <h3>Food Donation</h3>

                        <p>
                            Help prepare and organize
                            food donations for
                            communities that need
                            additional support.
                        </p>

                    </div>

                </article>

                <article class="gt-about-card">

                    <img
                        src="images/teamwork.jpeg"
                        alt="Volunteers working together"
                        loading="lazy"
                    >

                    <div class="gt-about-card-body">

                        <h3>Community Support</h3>

                        <p>
                            Work with other volunteers,
                            meet new people and take part
                            in activities that bring
                            communities together.
                        </p>

                    </div>

                </article>

            </div>

        </section>


        <!-- HOW IT WORKS -->

        <section class="gt-about-steps">

            <div class="gt-about-heading">
                <h2>How Good Together Works</h2>
            </div>

            <div class="gt-steps-grid">

                <div>
                    <div class="gt-step-number">01</div>
                    <h3>Discover</h3>
                    <p>
                        Browse volunteering activities
                        by city and category.
                    </p>
                </div>

                <div>
                    <div class="gt-step-number">02</div>
                    <h3>Register</h3>
                    <p>
                        Choose an opportunity and
                        register for the event.
                    </p>
                </div>

                <div>
                    <div class="gt-step-number">03</div>
                    <h3>Participate</h3>
                    <p>
                        Attend the activity and
                        contribute to your community.
                    </p>
                </div>

            </div>

        </section>


        <!-- FINAL ACTION -->

        <section class="gt-about-cta">

            <h2>Be Part of Something Meaningful</h2>

            <p>
                Your next opportunity to make
                a difference starts here.
            </p>

            <a href="opportunities.php"
               class="gt-about-button">
                Find Volunteer Opportunities
            </a>

        </section>

    </main>

    <footer>
        <p>&copy; 2026 Good Together.</p>
    </footer>

</div>

</body>
</html>
