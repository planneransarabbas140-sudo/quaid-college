import { ArrowUpRight } from "lucide-react"

import { ShaderBackground } from "@/components/ui/hero-shader"

const HERO_IMAGE =
  "https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=1200&q=80"

export default function DemoOne() {
  return (
    <div className="m-6 w-full">
      <ShaderBackground>
        <img
          src={HERO_IMAGE}
          alt=""
          aria-hidden="true"
          className="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-20"
        />

        <header className="relative z-20 flex items-center justify-between p-6">
          <div className="flex items-center">
            <svg
              width="24"
              height="24"
              viewBox="0 0 392.02 324.6"
              fill="currentColor"
              xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true"
            >
              <path
                fill="#fff200"
                d="M268.08,0c-27.4,0-51.41,4.43-72.07,13.26C175.36,4.43,151.35,0,123.95,0H0v324.6h123.95c27.37,0,51.38-4.58,72.07-13.7,20.69,9.12,44.7,13.7,72.07,13.7h123.95V0h-123.95ZM324.09,268.36h-47.91c-20.25,0-37.3-4.05-51.18-12.15-12.28-7.17-21.94-17.41-28.99-30.7h0s0,0,0,0c0,0,0,0,0,0h0c-7.05,13.29-16.71,23.53-28.99,30.7-13.87,8.1-30.93,12.15-51.18,12.15h-47.91V56.24h47.91c19.8,0,36.67,4.01,50.61,12.04,12.51,7.2,22.35,17.47,29.55,30.77h0s0,0,0,0c0,0,0,0,0,0h0c7.2-13.3,17.04-23.57,29.55-30.77,13.95-8.02,30.82-12.04,50.61-12.04h47.91v212.13Z"
              />
            </svg>
          </div>

          <nav className="flex items-center space-x-2">
            <a
              href="#"
              className="rounded-full px-3 py-2 text-xs font-light text-white/80 transition-all duration-200 hover:bg-white/10 hover:text-white"
            >
              Products
            </a>
            <a
              href="#"
              className="rounded-full px-3 py-2 text-xs font-light text-white/80 transition-all duration-200 hover:bg-white/10 hover:text-white"
            >
              Pricing
            </a>
            <a
              href="#"
              className="rounded-full px-3 py-2 text-xs font-light text-white/80 transition-all duration-200 hover:bg-white/10 hover:text-white"
            >
              Docs
            </a>
          </nav>

          <div
            id="gooey-btn"
            className="group relative flex items-center"
            style={{ filter: "url(#gooey-filter)" }}
          >
            <button
              type="button"
              className="absolute right-0 z-0 flex h-8 -translate-x-10 cursor-pointer items-center justify-center rounded-full bg-white px-2.5 py-2 text-xs font-normal text-black transition-all duration-300 group-hover:-translate-x-19 hover:bg-white/90"
              aria-label="Open login"
            >
              <ArrowUpRight className="h-3 w-3" />
            </button>
            <button
              type="button"
              className="z-10 flex h-8 cursor-pointer items-center rounded-full bg-white px-6 py-2 text-xs font-normal text-black transition-all duration-300 hover:bg-white/90"
            >
              Login
            </button>
          </div>
        </header>

        <main className="absolute bottom-8 left-8 z-20 max-w-lg">
          <div className="text-left">
            <div
              className="relative mb-4 inline-flex items-center rounded-full bg-white/5 px-3 py-1 backdrop-blur-sm"
              style={{ filter: "url(#glass-effect)" }}
            >
              <div className="absolute top-0 right-1 left-1 h-px rounded-full bg-gradient-to-r from-transparent via-white/20 to-transparent" />
              <span className="relative z-10 text-xs font-light text-white/90">
                ✨ New Design Ideas
              </span>
            </div>

            <h1 className="mb-4 text-5xl leading-14 tracking-tight text-white md:text-6xl">
              <span className="instrument">Beautiful</span> Design
              <br />
              <span className="font-bold tracking-tight text-white">Experiences</span>
            </h1>

            <p className="mb-4 text-xs leading-relaxed font-light text-white/70">
              Discover the essence of creativity in our exquisite collection of top-tier abstract
              design assets. Each piece is a blend of beauty and utility, perfect for elevating any
              project.
            </p>

            <div className="flex flex-wrap items-center gap-4">
              <button
                type="button"
                className="cursor-pointer rounded-full border border-white/30 bg-transparent px-8 py-3 text-xs font-normal text-white transition-all duration-200 hover:border-white/50 hover:bg-white/10"
              >
                Pricing
              </button>
              <button
                type="button"
                className="cursor-pointer rounded-full bg-white px-8 py-3 text-xs font-normal text-black transition-all duration-200 hover:bg-white/90"
              >
                Get Started
              </button>
            </div>
          </div>
        </main>
      </ShaderBackground>
    </div>
  )
}
