/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  swcMinify: true,
  async rewrites() {
    // Served before the [...postpath] catch-all route
    return [{ source: '/iq-test', destination: '/iq-test.html' }]
  },
}

module.exports = nextConfig
