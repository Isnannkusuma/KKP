import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import { index as dokumenIndex } from '@/routes/dokumen';
import { index as kategoriIndex } from '@/routes/kategori';

const items = [
    { title: 'Dashboard', href: dashboard(), icon: '/Dashboard.png' },
    { title: 'Dokumen', href: dokumenIndex(), icon: '/document.png' },
    {
        title: 'Kategori Dokumen',
        href: kategoriIndex(),
        icon: '/document-category.png',
    },
    { title: 'Service API', href: '/service-api', icon: '/service-api.png' },
    {
        title: 'Personalisasi Chatbot',
        href: '/pengaturan-chatbot',
        icon: '/chatbot-personalize.png',
    },
];

// Persist across remounts so the glass slides from the previous page's position.
let lastIndex = 0;

export function MobileNavbar() {
    const { isCurrentUrl } = useCurrentUrl();
    const found = items.findIndex((item) => isCurrentUrl(item.href));
    const activeIndex = found === -1 ? null : found;
    const [position, setPosition] = useState(lastIndex);
    const [hidden, setHidden] = useState(false);
    const lastY = useRef(0);

    useEffect(() => {
        if (activeIndex === null) {
            return;
        }

        lastIndex = activeIndex;
        const frame = requestAnimationFrame(() => setPosition(activeIndex));

        return () => cancelAnimationFrame(frame);
    }, [activeIndex]);

    useEffect(() => {
        const onScroll = (event: Event) => {
            const target = event.target;
            const y =
                target instanceof HTMLElement
                    ? target.scrollTop
                    : window.scrollY;
            const delta = y - lastY.current;

            if (Math.abs(delta) < 8) {
                return;
            }

            setHidden(delta > 0 && y > 40);
            lastY.current = y;
        };

        window.addEventListener('scroll', onScroll, {
            capture: true,
            passive: true,
        });

        return () => window.removeEventListener('scroll', onScroll, true);
    }, []);

    return (
        <nav
            aria-label="Navigasi utama"
            className={`fixed inset-x-3 bottom-3 z-50 h-[76px] drop-shadow-lg transition-transform duration-300 ease-out ${
                hidden ? 'translate-y-[calc(100%+2rem)]' : 'translate-y-0'
            }`}
        >
            {/* 1nav.png as a 3-slice background so the corners are not distorted */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0"
                style={{
                    borderStyle: 'solid',
                    borderWidth: '0 36px',
                    borderImage:
                        'url(/1nav.png) 0 72 fill / 0 36px / 0 stretch',
                }}
            />
            <div className="absolute inset-x-0 top-3 flex h-12 items-center">
                {activeIndex !== null && (
                    <img
                        src="/glass.png"
                        alt=""
                        aria-hidden="true"
                        className="pointer-events-none absolute top-0 left-0 h-12 w-1/5 object-fill px-1 transition-transform duration-500 ease-[cubic-bezier(0.34,1.4,0.64,1)]"
                        style={{
                            transform: `translateX(${position * 100}%)`,
                        }}
                    />
                )}
                {items.map((item, index) => (
                    <Link
                        key={item.title}
                        href={item.href}
                        prefetch
                        aria-label={item.title}
                        aria-current={
                            index === activeIndex ? 'page' : undefined
                        }
                        className="relative flex h-12 flex-1 items-center justify-center"
                    >
                        <img
                            src={item.icon}
                            alt=""
                            className="h-6 w-auto max-w-6 shrink-0 object-contain"
                        />
                    </Link>
                ))}
            </div>
        </nav>
    );
}
