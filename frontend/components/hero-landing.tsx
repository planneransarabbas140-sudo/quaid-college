import { ArrowUpRight, GraduationCap } from "lucide-react"

import { ShaderBackground } from "@/components/ui/hero-shader"

const CAMPUS_IMAGE =
  "https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=1200&q=80"

export default function HeroLanding() {
  return (
    <div className="w-full">
      <ShaderBackground>
        <img
          src={CAMPUS_IMAGE}
          alt=""
          aria-hidden="true"
          className="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-15"
        />

        <header className="relative z-20 flex items-center justify-between p-6">
          <div className="flex items-center gap-2">
            <GraduationCap className="h-7 w-7 text-[#4ec2b5]" strokeWidth={1.5} />
            <span className="text-sm font-semibold tracking-wide text-white">
              Quaid-e-Azam Group of Colleges
            </span>
          </div>

          <nav className="hidden items-center space-x-2 md:flex">
            <a
              href="/programs.php"
              className="rounded-full px-3 py-2 text-xs font-light text-white/80 transition-all duration-200 hover:bg-white/10 hover:text-white"
            >
              Programs
            </a>
            <a
              href="/modules/admissions/apply.php"
              className="rounded-full px-3 py-2 text-xs font-light text-white/80 transition-all duration-200 hover:bg-white/10 hover:text-white"
            >
              Admissions
            </a>
            <a
              href="/about.php"
              className="rounded-full px-3 py-2 text-xs font-light text-white/80 transition-all duration-200 hover:bg-white/10 hover:text-white"
            >
              About
            </a>
          </nav>

          <div
            id="gooey-btn"
            className="group relative flex items-center"
            style={{ filter: "url(#gooey-filter)" }}
          >
            <a
              href="/modules/auth/login.php"
              className="absolute right-0 z-0 flex h-8 -translate-x-10 items-center justify-center rounded-full bg-white px-2.5 py-2 text-xs font-normal text-black transition-all duration-300 group-hover:-translate-x-19 hover:bg-white/90"
              aria-label="Open portal"
            >
              <ArrowUpRight className="h-3 w-3" />
            </a>
            <a
              href="/modules/auth/login.php"
              className="z-10 flex h-8 items-center rounded-full bg-white px-6 py-2 text-xs font-normal text-black transition-all duration-300 hover:bg-white/90"
            >
              Student Portal
            </a>
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
                Admissions Open 2026
              </span>
            </div>

            <h1 className="mb-4 text-5xl leading-14 tracking-tight text-white md:text-6xl">
              <span className="instrument text-[#4ec2b5]">Excellence</span> in Education
              <br />
              <span className="font-bold tracking-tight text-white">Since 1998</span>
            </h1>

            <p className="mb-4 text-xs leading-relaxed font-light text-white/70">
              Discover programs across Rajanpur, Fazilpur, and Kot Mithan campuses. Modern
              facilities, dedicated faculty, and a legacy of academic achievement.
            </p>

            <div className="flex flex-wrap items-center gap-4">
              <a
                href="/programs.php"
                className="cursor-pointer rounded-full border border-white/30 bg-transparent px-8 py-3 text-xs font-normal text-white transition-all duration-200 hover:border-white/50 hover:bg-white/10"
              >
                View Programs
              </a>
              <a
                href="/modules/admissions/apply.php"
                className="cursor-pointer rounded-full bg-[#4ec2b5] px-8 py-3 text-xs font-normal text-[#0f2d48] transition-all duration-200 hover:bg-[#35a99c]"
              >
                Apply Now
              </a>
            </div>
          </div>
        </main>
      </ShaderBackground>
    </div>
  )
}
