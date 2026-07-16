<?php
/**
 * user/components/landing_guidelines.php
 * Guidelines component for the landing page.
 */
$t = $t ?? [];
?>
<section id="guidelines" class="landing-content" style="background-color: var(--bg-slate); border-radius: 20px; padding: 60px 40px; margin-top: 40px; margin-bottom: 80px; border: 1px solid var(--border-color);">
    <div class="landing-section-header" style="margin-bottom: 40px; text-align: center;">
        <h2 style="font-size: 28px; font-weight: 800; color: var(--primary-navy); margin-bottom: 12px;"><?php echo $t['guidelines_title']; ?></h2>
        <p style="color: var(--text-muted); font-size: 15px;"><?php echo $t['guidelines_subtitle']; ?></p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px;">
        <div style="background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <div style="background: rgba(14, 165, 233, 0.1); width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--accent-cyan);">
                <i data-feather="layout"></i>
            </div>
            <h4 style="font-size: 16px; font-weight: 700; color: var(--primary-navy); margin-bottom: 8px;"><?php echo $t['g1_title']; ?></h4>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6;"><?php echo $t['g1_desc']; ?></p>
        </div>
        
        <div style="background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <div style="background: rgba(14, 165, 233, 0.1); width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--accent-cyan);">
                <i data-feather="key"></i>
            </div>
            <h4 style="font-size: 16px; font-weight: 700; color: var(--primary-navy); margin-bottom: 8px;"><?php echo $t['g2_title']; ?></h4>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6;"><?php echo $t['g2_desc']; ?></p>
        </div>

        <div style="background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <div style="background: rgba(14, 165, 233, 0.1); width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--accent-cyan);">
                <i data-feather="check-circle"></i>
            </div>
            <h4 style="font-size: 16px; font-weight: 700; color: var(--primary-navy); margin-bottom: 8px;"><?php echo $t['g3_title']; ?></h4>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6;"><?php echo $t['g3_desc']; ?></p>
        </div>
    </div>
</section>
