/**
 * What every cross-origin embed on this site has in common.
 *
 * There are three now — YouTube, TikTok and Instagram Reels — and the two
 * rules below apply to all of them. They live here rather than in any one
 * platform's file so that a fourth cannot be added without meeting them.
 */

/**
 * Every embedded iframe must carry this, or the player refuses to start.
 *
 * nginx sends `Referrer-Policy: same-origin` for the whole site, which means a
 * cross-origin request carries no `Referer` at all — verified by watching one
 * arrive at our own access log as "-". An embedded player uses that header to
 * work out which site is embedding it; YouTube answers its absence with
 * "Video player configuration error 153" instead of the video, and the others
 * are no more forgiving.
 *
 * So the policy is relaxed on the iframes and **only** there.
 * `strict-origin-when-cross-origin` sends the bare origin —
 * `https://example.school` — and never the path, so the platform still learns
 * nothing about *which lesson* a student is reading. That is the part worth
 * protecting; the origin it necessarily knows already, because it is serving
 * the embed.
 *
 * Do not fix a future case of this by widening the nginx header: that would
 * leak the path to every other cross-origin destination on the site as well.
 * There is no JS test runner in this project, so this constant is the only
 * guard — a new iframe that omits it fails exactly the same way.
 */
export const EMBED_REFERRER_POLICY = 'strict-origin-when-cross-origin';

/**
 * The `allow` list handed to an embedded player.
 *
 * Deliberately short, and deliberately without `autoplay`: a lesson page that
 * starts making noise on its own is a worse classroom than one that does not.
 */
export const EMBED_ALLOW =
    'accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
