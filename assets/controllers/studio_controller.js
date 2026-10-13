import { Controller } from '@hotwired/stimulus';
import { loadFrames, drawSequence } from '../horizon-sequence.js';
import { loadEditFrames, drawEditScreen, editState } from '../edit-sequence.js';

export default class extends Controller {
  static targets = ['canvas', 'stage', 'fallback', 'toggle', 'framing', 'horizon', 'timecode', 'edit', 'editLabel'];

  connect() {
    this.element.dataset.studioState = 'connecting';
    this.connected = true;
    this.paused = false;
    this.generation = 0;
    this.controlsVisible = false; this.controlsAvailable = false;
    this.controlsObserver = new IntersectionObserver(entries => {
      this.controlsVisible = entries[0].isIntersecting;
      this.toggleTarget.hidden = !this.controlsVisible || !this.controlsAvailable;
    });
    this.controlsObserver.observe(this.element);
    this.motion = matchMedia('(prefers-reduced-motion: reduce)');
    this.mobile = matchMedia('(max-width: 1000px)');
    this.onPreference = () => this.restart();
    this.motion.addEventListener('change', this.onPreference);
    this.mobile.addEventListener('change', this.onPreference);
    this.restart();
  }

  restart() {
    this.cleanup();
    if (!this.connected || this.paused || this.motion.matches || navigator.connection?.saveData) { this.element.dataset.studioState = 'static'; return; }
    this.element.dataset.studioState = 'observing';
    const generation = this.generation;
    this.observer = new IntersectionObserver(entries => {
      if (entries.some(entry => entry.isIntersecting)) {
        this.observer.disconnect();
        this.load(generation);
      }
    }, { rootMargin: '200px' });
    this.observer.observe(this.element);
  }

  async load(generation) {
    this.element.dataset.studioState = 'loading';
    try {
      const [{ gsap }, { ScrollTrigger }] = await Promise.all([import('gsap'), import('gsap/ScrollTrigger')]);
      if (generation !== this.generation) return;
      gsap.registerPlugin(ScrollTrigger);
      this.gsap = gsap;
      this.setupHorizon(gsap, generation);
      this.setupEdit(gsap, generation);
      this.controlsAvailable = true;
      this.toggleTarget.hidden = !this.controlsVisible;
      const scenes = [...this.element.querySelectorAll('[data-scene]')];
      this.animationContext = gsap.context(() => {
        this.element.querySelectorAll('.destination').forEach((card, index) => {
          gsap.from(card, { x: this.mobile.matches ? 0 : -280 - index * 90, y: this.mobile.matches ? 45 : 100 + index * 35,
            rotationY: this.mobile.matches ? 0 : -55, rotationZ: this.mobile.matches ? 3 : -20,
            scale: this.mobile.matches ? .96 : .72, opacity: this.mobile.matches ? .8 : .15,
            ease: 'power2.out', scrollTrigger: { trigger: this.mobile.matches ? card : '.destinations',
              start: 'top 92%', end: this.mobile.matches ? 'top 55%' : 'center 53%', scrub: .65 } });
        });
        if (!this.mobile.matches) gsap.from('.studio-preview', { y: 180, rotationX: 24, rotationY: -16, scale: .86,
          ease: 'power2.out', scrollTrigger: { trigger: '.studio-preview', start: 'top 95%', end: 'center 66%', scrub: .8 } });
        this.element.querySelectorAll('.calendar-block').forEach(block => gsap.from(block, { x: 35, opacity: .3, ease: 'power2.out',
          scrollTrigger: { trigger: block, start: 'top 96%', end: 'top 76%', scrub: .6 } }));
        gsap.from('.prompt-card', { x: this.mobile.matches ? -12 : -55, y: 30, rotationZ: -5, ease: 'none',
          scrollTrigger: { trigger: '.ai-composition', start: 'top 90%', end: 'top 55%', scrub: .7 } });
        gsap.from('.ai-output', { x: this.mobile.matches ? 12 : 65, rotationY: this.mobile.matches ? 0 : -22, scale: .94, ease: 'none',
          scrollTrigger: { trigger: '.ai-output', start: 'top 92%', end: 'top 58%', scrub: .7 } });
        gsap.from('.timeline-preview', { y: 35, rotationZ: 3, ease: 'none',
          scrollTrigger: { trigger: '.timeline-preview', start: 'top 95%', end: 'top 75%', scrub: .7 } });

      }, this.element);
      this.setupPreviews(generation);
      if (this.mobile.matches) { this.element.dataset.studioState = 'mobile'; ScrollTrigger.refresh(); return; }
      const [THREE, { RoomEnvironment }, composer] = await Promise.all([import('three'), import('three/addons/environments/RoomEnvironment.js'), import('../screen-composer.js')]);
      this.composer = composer;
      this.screenAssets = await composer.loadMedia();
      if (generation !== this.generation) return;
      this.createWorld(THREE, RoomEnvironment);
      this.element.classList.add('is-immersive');
      this.element.dataset.studioState = 'webgl';
      this.animationContext.add(() => {
        const poses = [
          { px: 0, py: -1.45, pz: 0, rx: .04, ry: -.28, rz: -.09, scale: .78, orbit: -.45, light: 5 },
          { px: 2.55, py: 0, pz: .15, rx: .02, ry: -.08, rz: 0, scale: .86, orbit: .35, light: 9 },
          { px: -3.35, py: -.8, pz: 0, rx: -.08, ry: -.38, rz: -.16, scale: .82, orbit: 1.35, light: 7 },
          { px: 4.2, py: 1.5, pz: -2, rx: .32, ry: -.65, rz: .25, scale: .56, orbit: 2.4, light: 3 },
          { px: -3.4, py: -2.1, pz: -.8, rx: -.18, ry: .48, rz: -.25, scale: .48, orbit: 3.3, light: 8 },
          { px: -3, py: 0, pz: .2, rx: .04, ry: -.25, rz: -.14, scale: .96, orbit: 4.4, light: 6 },
        ];
        const atmospheres = [{cool:0, lift:-.3, spread:1}, {cool:.2, lift:0, spread:1}, {cool:0, lift:0, spread:1}, {cool:.8, lift:-1.8, spread:.55}, {cool:1, lift:0, spread:.8}, {cool:.1, lift:0, spread:1}];
        poses.forEach((pose, index) => Object.assign(pose, atmospheres[index], { freedom: index === 0 ? 1 : 0, capture: index === 1 ? 1 : 0, ribbon: [.42, .06, .14, .05, 0, .08][index] }));
        Object.assign(this.pose, poses[0]);
        // One scroll clock owns every pose: fast reverse scrolling cannot leave a later tween in control.
        const scrollState = { progress: 0 };
        let stops = [], distance = 1;
        const updatePose = () => {
          const position = scrollState.progress * distance;
          let index = 0;
          while (index < poses.length - 2 && position > stops[index + 1]) index++;
          const raw = Math.max(0, Math.min(1, (position - stops[index]) / (stops[index + 1] - stops[index])));
          // Hold the edit inside its framing rig before starting the next journey.
          const fraction = index === 1 ? Math.max(0, (raw - .6) / .4) : index === 4 ? Math.max(0, (raw - .78) / .22) : raw;
          const eased = (1 - Math.cos(fraction * Math.PI)) / 2;
          Object.keys(poses[0]).forEach(key => { this.pose[key] = poses[index][key] + (poses[index + 1][key] - poses[index][key]) * eased; });
          this.screenPhase = index + Math.max(0, Math.min(1, (fraction - .5) / .25));
          this.element.dataset.screen = String(Math.round(this.screenPhase));
          this.syncFilm?.();
          this.render();
        };
        const measure = () => {
          const height = this.stageTarget.clientHeight;
          distance = Math.max(1, this.element.scrollHeight - height);
          stops = scenes.map((section, index) => index ? Math.max(1, section.offsetTop - height * .12) : 0);
          updatePose();
        };
        measure();
        gsap.to(scrollState, { progress: 1, ease: 'none', onUpdate: updatePose,
          scrollTrigger: { trigger: this.element, start: 'top top', end: 'bottom bottom', scrub: .65, onRefresh: measure } });
        gsap.to('.hero-heading', { y: -90, opacity: 0, ease: 'none',
          scrollTrigger: { trigger: scenes[0], start: 'top top', end: '65% top', scrub: .5 } });
      });
      this.resizeWorld();
      ScrollTrigger.refresh();
      this.render();
      this.playVideo(generation);
    } catch (error) {
      this.cleanup();
      this.element.dataset.studioState = 'fallback';
      console.info('VydFlow : aperçu statique activé.', error.message);
    }
  }

  createWorld(THREE, RoomEnvironment) {
    this.resources = new Set();
    const keep = resource => { this.resources.add(resource); return resource; };
    const renderer = this.renderer = new THREE.WebGLRenderer({ canvas: this.canvasTarget, alpha: true, antialias: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(devicePixelRatio, 1.5));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.1;
    const scene = this.scene = new THREE.Scene();
    const camera = this.camera = new THREE.PerspectiveCamera(38, 1, .1, 80);
    camera.position.z = 11.5;
    const room = new RoomEnvironment();
    const pmrem = new THREE.PMREMGenerator(renderer);
    this.environment = pmrem.fromScene(room, .04);
    scene.environment = this.environment.texture;
    room.dispose();
    pmrem.dispose();
    const phone = this.phone = new THREE.Group();
    scene.add(phone);
    const rounded = (w, h, r) => {
      const s = new THREE.Shape(), x = -w / 2, y = -h / 2;
      s.moveTo(x + r, y); s.lineTo(x + w - r, y); s.quadraticCurveTo(x + w, y, x + w, y + r);
      s.lineTo(x + w, y + h - r); s.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
      s.lineTo(x + r, y + h); s.quadraticCurveTo(x, y + h, x, y + h - r);
      s.lineTo(x, y + r); s.quadraticCurveTo(x, y, x + r, y); return s;
    };
    const mesh = (geometry, material, parent, x = 0, y = 0, z = 0) => {
      const object = new THREE.Mesh(keep(geometry), keep(material));
      object.position.set(x, y, z); parent.add(object); return object;
    };
    mesh(new THREE.ExtrudeGeometry(rounded(2.25, 4.18, .3), { depth: .17, bevelEnabled: true, bevelSegments: 3, bevelSize: .045, bevelThickness: .045, curveSegments: 24 }),
      new THREE.MeshStandardMaterial({ color: 0x7e788b, metalness: 1, roughness: .22 }), phone);
    mesh(new THREE.ShapeGeometry(rounded(2.2, 4.12, .28)), new THREE.MeshStandardMaterial({ color: 0x15131a, metalness: .7, roughness: .3, side: THREE.DoubleSide }), phone, 0, 0, -.05);
    const screen = new THREE.ShapeGeometry(rounded(2.12, 3.99, .25), 32);
    const uv = screen.attributes.uv, position = screen.attributes.position;
    for (let i = 0; i < uv.count; i++) uv.setXY(i, .5 + position.getX(i) / 2.12 * (2.12 / 3.99) / (9 / 16), (position.getY(i) + 1.995) / 3.99);
    this.screenCanvas = document.createElement('canvas');
    this.screenCanvas.width = 540; this.screenCanvas.height = 960;
    this.screenContext = this.screenCanvas.getContext('2d');
    this.screenBuffers = [0, 1].map(() => { const canvas = document.createElement('canvas'); canvas.width = 540; canvas.height = 960; return canvas; });
    this.screenTexture = keep(new THREE.CanvasTexture(this.screenCanvas));
    this.screenTexture.colorSpace = THREE.SRGBColorSpace;
    this.screenPhase = 0;
    this.screenMaterial = new THREE.MeshBasicMaterial({ map: this.screenTexture });
    mesh(screen, this.screenMaterial, phone, 0, 0, .22);
    mesh(new THREE.ShapeGeometry(rounded(.67, .15, .075)), new THREE.MeshBasicMaterial({ color: 0x030305 }), phone, 0, 1.8, .235);
    mesh(new THREE.BoxGeometry(.04, .42, .1), new THREE.MeshStandardMaterial({ color: 0xaaa1b6, metalness: 1, roughness: .2 }), phone, 1.17, .65, .08);
    mesh(new THREE.BoxGeometry(.04, .22, .1), new THREE.MeshStandardMaterial({ color: 0xaaa1b6, metalness: 1, roughness: .2 }), phone, -1.17, .9, .08);
    // The framing rig shares the phone's camera and world units, so resize cannot misalign them.
    this.captureRig = new THREE.Group();
    this.captureMaterial = keep(new THREE.LineBasicMaterial({ color: 0xd9c7ff, transparent: true, opacity: 0, depthTest: false, depthWrite: false }));
    const corners = [], halfW = 1.19, halfH = 2.05, arm = .28;
    for (const x of [-1, 1]) for (const y of [-1, 1]) {
      corners.push(x * halfW, y * (halfH - arm), 0, x * halfW, y * halfH, 0);
      corners.push(x * halfW, y * halfH, 0, x * (halfW - arm), y * halfH, 0);
    }
    const framingGeometry = keep(new THREE.BufferGeometry());
    framingGeometry.setAttribute('position', new THREE.Float32BufferAttribute(corners, 3));
    this.captureRig.add(new THREE.LineSegments(framingGeometry, this.captureMaterial));
    scene.add(this.captureRig);
    this.captionPosition = new THREE.Vector3();
    // Film-strip sculptures: broad metallic ribbons with fine illuminated edit rails.
    this.ribbonMaterials = [];
    this.violet = new THREE.Color(0x7950c8);
    this.silver = new THREE.Color(0x617a8d);
    this.sculpture = new THREE.Group();
    scene.add(this.sculpture);
    for (let j = 0; j < 3; j++) {
      const vertices = [], indices = [], segments = 180;
      for (let i = 0; i <= segments; i++) {
        const angle = i / segments * Math.PI * 2;
        for (const edge of [-1, 1]) {
          const radius = 4.6 + j * .53 + edge * .12;
          vertices.push(Math.cos(angle) * radius, Math.sin(angle) * radius * .55, Math.sin(angle * 2 + j * .7) * .75);
        }
        if (i < segments) { const n = i * 2; indices.push(n, n + 1, n + 2, n + 1, n + 3, n + 2); }
      }
      const geometry = new THREE.BufferGeometry();
      geometry.setAttribute('position', new THREE.Float32BufferAttribute(vertices, 3));
      geometry.setIndex(indices); geometry.computeVertexNormals();
      const material = new THREE.MeshStandardMaterial({ color: j === 1 ? 0x7950c8 : 0x494152, metalness: .95, roughness: .32, envMapIntensity: .5, transparent: true, opacity: .42, depthWrite: false, side: THREE.DoubleSide });
      this.ribbonMaterials.push(material);
      mesh(geometry, material, this.sculpture);
    }
    this.sculpture.position.z = -2.5;
    this.sculpture.rotation.x = -.32;
    scene.add(new THREE.AmbientLight(0xbfc7ff, .45));
    const key = new THREE.DirectionalLight(0xf2edff, 4); key.position.set(3, 5, 5); scene.add(key);
    this.rim = new THREE.PointLight(0x9a60ff, 8, 30, 1); this.rim.position.set(-4, 1, 4); scene.add(this.rim);
    const cool = new THREE.DirectionalLight(0xbed4ff, 2); cool.position.set(4, -2, 2); scene.add(cool);
    this.pose = { px: 0, py: -1.45, pz: 0, rx: .04, ry: -.28, rz: -.09, scale: .78, orbit: -.45, light: 5 };
    this.cursor = { x: 0, y: 0 };
    this.cursorTweenX = this.gsap.quickTo(this.cursor, 'x', { duration: .75, ease: 'power3.out', onUpdate: () => this.render() });
    this.cursorTweenY = this.gsap.quickTo(this.cursor, 'y', { duration: .75, ease: 'power3.out', onUpdate: () => this.render() });
    this.onPointer = event => {
      this.cursorTweenX((event.clientX / innerWidth - .5) * .34);
      this.cursorTweenY((event.clientY / innerHeight - .5) * .2);
    };
    if (matchMedia('(pointer:fine)').matches) window.addEventListener('pointermove', this.onPointer, { passive: true });
    this.onPointerLeave = event => { if (!event.relatedTarget) { this.cursorTweenX(0); this.cursorTweenY(0); } };
    window.addEventListener('pointerout', this.onPointerLeave);
    window.addEventListener('blur', this.onPointerLeave);
    this.resizeObserver = new ResizeObserver(() => this.resizeWorld());
    this.resizeObserver.observe(this.stageTarget);
    this.onContextLost = event => { event.preventDefault(); this.cleanup(); };
    this.canvasTarget.addEventListener('webglcontextlost', this.onContextLost);
    this.stageTarget.classList.add('ready');
  }

  resizeWorld() {
    if (!this.renderer) return;
    const { width, height } = this.stageTarget.getBoundingClientRect();
    if (!width || !height) return;
    this.renderer.setSize(width, height, false);
    this.camera.aspect = width / height;
    this.camera.updateProjectionMatrix();
    this.render();
  }

  render() {
    if (!this.renderer || !this.pose) return;
    this.drawPhoneScreen();
    const p = this.pose;
    // Compress horizontal staging on smaller desktop viewports, preserving the text columns.
    const widthFactor = Math.min(1, this.camera.aspect / 1.65);
    const cursorWeight = p.freedom || 0;
    this.phone.position.set(p.px * widthFactor + this.cursor.x * 1.65 * cursorWeight, p.py - this.cursor.y * 1.6 * cursorWeight, p.pz);
    this.phone.rotation.set(p.rx + this.cursor.y * cursorWeight, p.ry + this.cursor.x * cursorWeight, p.rz - this.cursor.x * .12 * cursorWeight);
    this.phone.scale.setScalar(p.scale);
    this.captureRig.position.set(2.55 * widthFactor, 0, .6);
    this.captureMaterial.opacity = p.capture || 0;
    this.captionPosition.set(2.55 * widthFactor, -2.35, .6).project(this.camera);
    this.framingTarget.style.left = ((this.captionPosition.x + 1) / 2 * 100) + '%';
    this.framingTarget.style.top = ((1 - this.captionPosition.y) / 2 * 100) + '%';
    this.framingTarget.style.opacity = String(p.capture || 0);
    this.element.dataset.capture = (p.capture || 0).toFixed(3);
    this.sculpture.position.y = p.lift || 0;
    this.sculpture.scale.y = p.spread || 1;
    this.ribbonMaterials.forEach((material, index) => {
      material.opacity = p.ribbon ?? .42;
      material.color.lerpColors(this.violet, this.silver, p.cool || 0).multiplyScalar(index === 1 ? 1 : .55);
    });
    this.sculpture.rotation.z = p.orbit;
    this.sculpture.rotation.y = Math.sin(p.orbit) * .45;
    this.rim.intensity = p.light;
    this.renderer.render(this.scene, this.camera);
  }

  drawPhoneScreen() {
    if (!this.screenContext || !this.composer) return;
    const phase = Math.max(0, Math.min(5, this.screenPhase || 0));
    const first = Math.floor(phase), blend = phase - first;
    const c = this.screenContext;
    this.composer.drawScreen(this.screenBuffers[0].getContext('2d'), this.screenAssets, first, phase % 1, this.video, this.horizonFrames || [], this.horizonProgress || 0, this.editFrames || [], this.editProgress || 0);
    c.clearRect(0, 0, 540, 960); c.globalAlpha = 1; c.drawImage(this.screenBuffers[0], 0, 0);
    if (blend > 0) {
      this.composer.drawScreen(this.screenBuffers[1].getContext('2d'), this.screenAssets, Math.min(5, first + 1), blend, this.video, this.horizonFrames || [], this.horizonProgress || 0, this.editFrames || [], this.editProgress || 0);
      c.globalAlpha = blend; c.drawImage(this.screenBuffers[1], 0, 0); c.globalAlpha = 1;
    }
    this.screenTexture.needsUpdate = true;
  }

  setupEdit(gsap, generation) {
    this.element.classList.add('has-edit-motion');
    this.editProgress = 0;
    this.editContext = this.editTarget.getContext('2d');
    const clock = { progress: 0 };
    this.editAnimation = gsap.context(() => {
      gsap.to(clock, { progress: 1, ease: 'none', onUpdate: () => {
        this.editProgress = clock.progress; this.drawEdit(); this.render();
      }, scrollTrigger: { trigger: this.mobile.matches ? '.mobile-edit-showcase' : '#studio',
        start: this.mobile.matches ? 'top 65%' : 'top 15%',
        end: this.mobile.matches ? 'bottom 85%' : 'bottom 75%', scrub: .35 } });
    }, this.element);
    this.editObserver = new IntersectionObserver(entries => {
      if (!entries.some(entry => entry.isIntersecting)) return;
      this.editObserver.disconnect();
      loadEditFrames().then(frames => {
        if (generation !== this.generation) return;
        this.editFrames = frames; this.drawEdit(); this.render();
      });
    }, { rootMargin: '600px' });
    this.editObserver.observe(this.element.querySelector('#studio'));
    this.drawEdit();
  }

  drawEdit() {
    const ready = this.editFrames?.length === 4 && this.editFrames.every(Boolean);
    const state = editState(ready ? this.editProgress : 0);
    if (ready && this.editContext && drawEditScreen(this.editContext, this.editFrames, state.progress)) this.editTarget.classList.add('is-ready');
    this.editTarget.dataset.frame = String(state.index);
    this.element.dataset.editFrame = String(state.index);
    this.editLabelTarget.textContent = 'PLAN 0' + (state.index + 1) + ' · ' + ['PRÉPARATION', 'LE GESTE', 'LA FORME', 'LE RÉSULTAT'][state.index] + ' · ' + state.timecode;
    this.element.querySelector('.edit-scrub b').style.left = (state.progress * 100) + '%';
    this.element.querySelectorAll('[data-edit-frame]').forEach((frame, i) => frame.classList.toggle('is-current', i === state.index));
  }

  setupHorizon(gsap, generation) {
    this.element.classList.add('has-horizon-motion');
    this.horizonProgress = 0;
    this.horizonContext = this.horizonTarget.getContext('2d');
    const sequence = { progress: 0 };
    // Keep the image clock separate from the phone's journey, shared by both render surfaces.
    this.horizonAnimation = gsap.context(() => {
      gsap.to(sequence, { progress: 1, ease: 'none', onUpdate: () => {
        this.horizonProgress = sequence.progress;
        this.drawHorizon();
        this.render();
      }, scrollTrigger: { trigger: '.horizon-runway', start: 'top 65%', end: 'bottom 85%', scrub: .35 } });
    }, this.element);
    this.horizonObserver = new IntersectionObserver(entries => {
      if (!entries.some(entry => entry.isIntersecting)) return;
      this.horizonObserver.disconnect();
      loadFrames().then(frames => {
        if (generation !== this.generation) return;
        this.horizonFrames = frames;
        this.drawHorizon();
        this.render();
      });
    }, { rootMargin: '600px' });
    this.horizonObserver.observe(this.horizonTarget);
  }

  drawHorizon() {
    const progress = this.horizonProgress || 0;
    if (this.horizonContext && this.horizonFrames && drawSequence(this.horizonContext, this.horizonFrames, progress)) this.horizonTarget.classList.add('is-ready');
    this.timecodeTarget.textContent = '00:0' + Math.min(6, Math.floor(progress * 6));
    const index = Math.min(5, Math.floor(progress * 6));
    this.element.dataset.horizonFrame = String(index);
    this.element.querySelector('.timeline-track b').style.left = (progress * 99) + '%';
    this.element.querySelectorAll('.timeline-track i').forEach((frame, i) => frame.classList.toggle('is-current', i === index));
  }

  setupPreviews(generation) {
    this.previewVideos = [...this.element.querySelectorAll('[data-preview-src]')].filter(video => !video.hasAttribute('data-mobile-only') || this.mobile.matches);
    const update = video => {
      if (generation !== this.generation) return;
      if (video.dataset.inView !== 'true' || document.hidden) { video.pause(); return; }
      if (!video.getAttribute('src')) video.src = video.dataset.previewSrc;
      video.play().then(() => {
        if (generation !== this.generation || document.hidden || video.dataset.inView !== 'true') { video.pause(); return; }
        video.classList.add('is-playing');
      }).catch(() => video.classList.remove('is-playing'));
    };
    this.previewObserver = new IntersectionObserver(entries => entries.forEach(entry => {
      entry.target.dataset.inView = String(entry.isIntersecting); update(entry.target);
    }), { threshold: .25 });
    this.previewVideos.forEach(video => this.previewObserver.observe(video));
    this.onPreviewVisibility = () => this.previewVideos?.forEach(update);
    document.addEventListener('visibilitychange', this.onPreviewVisibility);
  }

  playVideo(generation) {
    const video = this.video = document.createElement('video');
    Object.assign(video, { src: this.element.dataset.videoSrc, muted: true, loop: true, playsInline: true, preload: 'metadata' });
    let requesting = false;
    const stop = () => { video.pause(); video.cancelVideoFrameCallback?.(this.frameCallback); this.frameCallback = null; this.renderer?.setAnimationLoop(null); };
    const frame = () => {
      if (generation !== this.generation || video.paused) return;
      this.render(); this.frameCallback = video.requestVideoFrameCallback(frame);
    };
    this.syncFilm = () => {
      const phase = this.screenPhase || 0;
      const active = this.visible && !document.hidden && phase < 1;
      if (!active) { stop(); return; }
      if (!video.paused || requesting) return;
      requesting = true;
      video.play().then(() => {
        requesting = false;
        if (generation !== this.generation || !this.visible || document.hidden || this.screenPhase >= 1) { stop(); return; }
        if (video.requestVideoFrameCallback) this.frameCallback = video.requestVideoFrameCallback(frame);
        else this.renderer?.setAnimationLoop(() => this.render());
      }).catch(() => { requesting = false; this.render(); });
    };
    this.onVisibility = () => this.syncFilm?.();
    document.addEventListener('visibilitychange', this.onVisibility);
    this.visibilityObserver = new IntersectionObserver(entries => {
      this.visible = entries[0].isIntersecting; this.syncFilm?.();
    });
    this.visibilityObserver.observe(this.element);
  }

  toggleMotion() {
    this.paused = !this.paused;
    this.toggleTarget.setAttribute('aria-pressed', String(this.paused));
    this.toggleTarget.textContent = this.paused ? 'Activer les animations' : 'Mettre les animations en pause';
    this.restart();
  }

  cleanup() {
    this.generation++;
    this.controlsAvailable = this.paused;
    this.toggleTarget.hidden = !this.controlsVisible || !this.controlsAvailable;
    this.element.classList.remove('has-edit-motion');
    this.editObserver?.disconnect();
    this.editAnimation?.revert(); this.editAnimation = null;
    this.editFrames = null; this.editContext = null; this.editProgress = 0;
    this.editTarget.classList.remove('is-ready');
    this.drawEdit();
    this.element.classList.remove('has-horizon-motion');
    this.horizonObserver?.disconnect();
    this.horizonAnimation?.revert(); this.horizonAnimation = null;
    this.horizonFrames = null; this.horizonContext = null;
    this.horizonTarget.classList.remove('is-ready');
    this.timecodeTarget.textContent = '00:00';
    this.element.querySelector('.timeline-track b').style.left = '0%';
    this.element.querySelectorAll('.timeline-track i').forEach(frame => frame.classList.remove('is-current'));
    this.observer?.disconnect();
    this.resizeObserver?.disconnect();
    this.visibilityObserver?.disconnect();
    this.previewObserver?.disconnect();
    document.removeEventListener('visibilitychange', this.onPreviewVisibility);
    this.previewVideos?.forEach(video => { video.pause(); video.classList.remove('is-playing'); video.removeAttribute('src'); video.load(); });
    this.previewVideos = null;
    this.animationContext?.revert();
    this.animationContext = null;
    window.removeEventListener('pointermove', this.onPointer);
    window.removeEventListener('pointerout', this.onPointerLeave);
    window.removeEventListener('blur', this.onPointerLeave);
    this.cursorTweenX?.tween.kill(); this.cursorTweenY?.tween.kill();
    this.cursorTweenX = null; this.cursorTweenY = null;
    document.removeEventListener('visibilitychange', this.onVisibility);
    this.canvasTarget.removeEventListener('webglcontextlost', this.onContextLost);
    this.renderer?.setAnimationLoop(null);
    if (this.video) { this.video.cancelVideoFrameCallback?.(this.frameCallback); this.video.pause(); this.video.removeAttribute('src'); this.video.load(); this.video = null; }
    this.syncFilm = null; this.frameCallback = null;
    this.screenContext = null; this.screenCanvas = null; this.screenBuffers = null; this.screenTexture = null;
    this.screenAssets = null; this.composer = null;
    this.resources?.forEach(resource => resource.dispose());
    this.resources?.clear();
    this.environment?.dispose(); this.environment = null;
    this.renderer?.dispose(); this.renderer = null;
    this.framingTarget.style.opacity = '0';
    this.stageTarget.classList.remove('ready');
    this.element.classList.remove('is-immersive');
  }

  disconnect() {
    this.connected = false;
    this.cleanup();
    this.controlsObserver?.disconnect();
    this.motion.removeEventListener('change', this.onPreference);
    this.mobile.removeEventListener('change', this.onPreference);
  }
}
