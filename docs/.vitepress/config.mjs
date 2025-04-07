import {defineConfig} from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
    title: "ODY Framework",
    description: "Docs",
    themeConfig: {
        // https://vitepress.dev/reference/default-theme-config
        nav: [
            {text: 'Home', link: '/'},
            {text: 'Docs', link: '/docs'},
            // { text: 'Reference', link: '/docs' }
        ],

        sidebar: {
            '/docs/': {base: '/docs/', items: sidebarDocs()},
            '/reference/': {base: '/reference/', items: sidebarReference()}
        },

        socialLinks: [
            {icon: 'github', link: 'https://github.com/ody-dev'}
        ],

        footer: {
            message: 'Released under the MIT License.',
            copyright: 'Copyright © 2025 ODY'
        },

        search: {
            provider: 'local'
        },
    }
})

function sidebarDocs() {
    return [
        {text: 'Introduction', link: 'introduction'},
        {text: 'Getting Started', link: 'getting-started'},
        {text: 'Lifecycle', link: 'lifecycle'},
        {
            text: 'Foudation',
            collapsed: false,
            items: [
                {text: 'Service Providers', base: '/docs/foundation/', link: 'service-providers'},
                {text: 'Middleware', base: '/docs/foundation/', link: 'middleware'},
                {text: 'Request', base: '/docs/foundation/', link: 'request'},
                {text: 'Response', base: '/docs/foundation/', link: 'response'},
                {text: 'Routing', base: '/docs/foundation/', link: 'routing'},
                {text: 'Exception Handler', base: '/docs/foundation/', link: 'exception-handler'},
                {text: 'Cache', base: '/docs/foundation/', link: 'cache'},
                {text: 'Logging', base: '/docs/foundation/', link: 'logging'},
            ]
        },
        {
            text: 'Server',
            collapsed: true,
            items: [
                {text: 'HTTP server', base: '/docs/server/', link: 'http-server'},
                {text: 'Websocket server', base: '/docs/server/', link: 'websocket-server'}
            ]
        },
        {
            text: 'Client',
            collapsed: true,
            items: [
                {text: 'HTTP client', base: '/docs/client/', link: 'http-client'},
            ]
        },
        {
            text: 'Database',
            collapsed: true,
            items: [
                {text: 'DBAL', base: '/docs/database/', link: 'dbal'},
                {text: 'Doctrine ORM', base: '/docs/database/', link: 'doctrine'},
                {text: 'Connection Pool', base: '/docs/database/', link: 'connection-pool'},
            ]
        },
        {
            text: 'Modules',
            collapsed: true,
            items: [
                // {
                //   text: 'AMQP',
                //   collapsed: true,
                //   items: [
                //     { text: 'Introduction', base: '/docs/modules/amqp/', link: 'amqp' },
                //     { text: 'Installation', base: '/docs/modules/amqp/', link: 'process' },
                //     { text: 'Producers', base: '/docs/modules/amqp/', link: 'cqrs' },
                //     { text: 'Consumers', base: '/docs/modules/amqp/', link: 'task' },
                //   ]
                // },
                // {
                //   text: 'CQRS',
                //   collapsed: true,
                //   items: [
                //     { text: 'Introduction', base: '/docs/modules/cqrs/', link: 'introduction' },
                //     { text: 'Installation', base: '/docs/modules/cqrs/', link: 'installation' },
                //     { text: 'Commands', base: '/docs/modules/cqrs/', link: 'commands' },
                //     { text: 'Queries', base: '/docs/modules/cqrs/', link: 'queries' },
                //     { text: 'Events', base: '/docs/modules/cqrs/', link: 'events' },
                //     { text: 'Middleware', base: '/docs/modules/cqrs/', link: 'middleware' },
                //     { text: 'Async', base: '/docs/modules/cqrs/', link: 'async' },
                //   ]
                // },
                {text: 'AMQP', base: '/docs/modules/', link: 'amqp'},
                {text: 'CQRS', base: '/docs/modules/', link: 'cqrs'},
                {text: 'Tasks', base: '/docs/modules/', link: 'task'},
                {text: 'Process', base: '/docs/modules/', link: 'process'},
                {text: 'Scheduler', base: '/docs/modules/', link: 'scheduler'},
            ]
        },
    ]
}

function sidebarReference() {
    return []
}
