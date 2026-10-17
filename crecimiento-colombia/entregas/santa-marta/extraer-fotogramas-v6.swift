import Foundation
import AVFoundation
import AppKit
// Extracción técnica de fotogramas reales, sin IA ni retoque.
let root = URL(fileURLWithPath: CommandLine.arguments[1])
let video = root.appendingPathComponent("imagenes/revision-v6/originales/SM-H6-05.mp4")
guard FileManager.default.fileExists(atPath: video.path) else {
 print("Original MP4 pendiente de descarga completa; no se extraen fotogramas.")
 exit(2)
}
let asset = AVURLAsset(url: video)
let generator = AVAssetImageGenerator(asset: asset)
generator.appliesPreferredTrackTransform = true
generator.requestedTimeToleranceBefore = .zero
generator.requestedTimeToleranceAfter = .zero
let folder = root.appendingPathComponent("imagenes/revision-v6/fotogramas")
try FileManager.default.createDirectory(at:folder,withIntermediateDirectories:true)
for second in [1,5,9,13] {
 var actual = CMTime.zero
 let image: CGImage
 do { image = try generator.copyCGImage(at:CMTime(seconds:Double(second),preferredTimescale:600),actualTime:&actual) }
 catch { print("Extracción bloqueada o decodificación no disponible: \(error)"); exit(3) }
 let bitmap = NSBitmapImageRep(cgImage:image)
 let data = bitmap.representation(using:.jpeg,properties:[.compressionFactor:0.95])!
 try data.write(to:folder.appendingPathComponent("SM-H6-05-t\(second)s.jpg"))
 print("frame \(second)s -> actual \(actual.seconds)s, \(image.width)x\(image.height)")
}
