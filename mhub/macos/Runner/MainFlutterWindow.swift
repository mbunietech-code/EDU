import Cocoa
import FlutterMacOS

class MainFlutterWindow: NSWindow {
  override func awakeFromNib() {
    let flutterViewController = FlutterViewController()
    let windowFrame = self.frame
    self.contentViewController = flutterViewController
    self.setFrame(windowFrame, display: true)

    // Always open at the size in MainMenu.xib (1280x720, the same as Windows),
    // so the website-style sidebar layout (900 px and wider) shows. Without this
    // macOS reopens the window at whatever size it had last time.
    self.isRestorable = false

    RegisterGeneratedPlugins(registry: flutterViewController)

    super.awakeFromNib()
  }
}
