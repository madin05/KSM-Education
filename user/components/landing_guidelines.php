<?php
/**
 * user/components/landing_guidelines.php
 * Guidelines component for the landing page.
 */
$t = $t ?? [];
?>
<section id="guidelines" class="landing-guidelines-section">
    <div class="landing-section-header" style="margin-bottom: 40px; text-align: center;">
        <h2 style="font-size: 28px; font-weight: 800; color: var(--primary-navy); margin-bottom: 12px;"><?php echo $t['guidelines_title']; ?></h2>
        <p style="color: var(--text-muted); font-size: 15px;"><?php echo $t['guidelines_subtitle']; ?></p>
    </div>

    <div class="guidelines-grid">
        <div class="guidelines-card">
            <div class="guidelines-icon-wrapper">
                <i data-feather="layout"></i>
            </div>
            <h4><?php echo $t['g1_title']; ?></h4>
            <p><?php echo $t['g1_desc']; ?></p>
        </div>
        
        <div class="guidelines-card">
            <div class="guidelines-icon-wrapper">
                <i data-feather="key"></i>
            </div>
            <h4><?php echo $t['g2_title']; ?></h4>
            <p><?php echo $t['g2_desc']; ?></p>
        </div>

        <div class="guidelines-card">
            <div class="guidelines-icon-wrapper">
                <i data-feather="check-circle"></i>
            </div>
            <h4><?php echo $t['g3_title']; ?></h4>
            <p><?php echo $t['g3_desc']; ?></p>
        </div>
    </div>
</section>
