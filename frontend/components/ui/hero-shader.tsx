import type React from "react"

import { MeshGradient } from "@paper-design/shaders-react"

interface ShaderBackgroundProps {
  children: React.ReactNode
}

export function ShaderBackground({ children }: ShaderBackgroundProps) {
  return (
    <div className="relative min-h-[650px] w-full overflow-hidden">
      <svg className="absolute inset-0 h-0 w-0" aria-hidden="true">
        <defs>
          <filter id="glass-effect" x="-50%" y="-50%" width="200%" height="200%">
            <feTurbulence baseFrequency="0.005" numOctaves="1" result="noise" />
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="0.3" />
            <feColorMatrix
              type="matrix"
              values="1 0 0 0 0.02
                      0 1 0 0 0.02
                      0 0 1 0 0.05
                      0 0 0 0.9 0"
              result="tint"
            />
          </filter>
          <filter id="gooey-filter" x="-50%" y="-50%" width="200%" height="200%">
            <feGaussianBlur in="SourceGraphic" stdDeviation="4" result="blur" />
            <feColorMatrix
              in="blur"
              mode="matrix"
              values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9"
              result="gooey"
            />
            <feComposite in="SourceGraphic" in2="gooey" operator="atop" />
          </filter>
        </defs>
      </svg>

      <MeshGradient
        className="absolute inset-0 h-full w-full"
        colors={["#0f2d48", "#4ec2b5", "#ffffff", "#1a4060", "#4c1d95"]}
        speed={0.3}
        distortion={0.85}
        swirl={0.15}
      />
      <MeshGradient
        className="absolute inset-0 h-full w-full opacity-60"
        colors={["#0f2d48", "#ffffff", "#4ec2b5", "#0f2d48"]}
        speed={0.2}
        distortion={0.4}
        swirl={0.9}
        grainOverlay={0.08}
      />

      {children}
    </div>
  )
}
